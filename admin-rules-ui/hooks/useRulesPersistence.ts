import { serializeDocument, stringT, type GroRulesEditorConfig, type UiRule } from '../ruleModel';
import { useCallback, useState } from 'react';

export interface SaveResult {
  ok: boolean;
  message: string;
}

export function documentJson(version: string, uiRules: UiRule[], cfg: GroRulesEditorConfig): string {
  const doc = serializeDocument(version, uiRules, cfg.defaultVersion ?? '1.0.0');
  return JSON.stringify(doc, null, 2);
}

export function useRulesPersistence(cfg: GroRulesEditorConfig) {
  const [savedSnapshot, setSavedSnapshot] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [saveNotice, setSaveNotice] = useState<string | null>(null);

  const markSaved = useCallback((json: string): void => {
    setSavedSnapshot(json);
    setSaveNotice(null);
  }, []);

  const hasUnsavedChanges = useCallback(
    (version: string, uiRules: UiRule[]): boolean => {
      if (savedSnapshot === null) return false;
      return documentJson(version, uiRules, cfg) !== savedSnapshot;
    },
    [cfg, savedSnapshot],
  );

  const persistDocument = useCallback(
    async (version: string, uiRules: UiRule[]): Promise<SaveResult> => {
      const json = documentJson(version, uiRules, cfg);
      const url = cfg.saveAjaxUrl;
      const action = cfg.saveAction ?? 'gro_rules_save';
      const nonce = cfg.saveNonce;

      if (!url || !nonce) {
        return { ok: false, message: stringT(cfg, 'saveFailed', 'Rules could not be saved: %s').replace('%s', 'Missing save configuration') };
      }

      setIsSaving(true);
      setSaveNotice(null);

      try {
        const body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', nonce);
        body.set('gro_rules_json', json);

        const res = await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString(),
        });

        const payload = (await res.json()) as { success?: boolean; data?: { message?: string }; message?: string };

        if (!res.ok || !payload.success) {
          const errMsg =
            (payload.data && typeof payload.data === 'object' && 'message' in payload.data
              ? String((payload.data as { message?: string }).message)
              : null) ??
            payload.message ??
            res.statusText;
          const msg = stringT(cfg, 'saveFailed', 'Rules could not be saved: %s').replace('%s', errMsg);
          setSaveNotice(msg);
          return { ok: false, message: msg };
        }

        const okMsg =
          (payload.data && typeof payload.data === 'object' && 'message' in payload.data
            ? String((payload.data as { message?: string }).message)
            : null) ?? stringT(cfg, 'saveSuccess', 'Rules saved.');
        markSaved(json);
        setSaveNotice(okMsg);
        return { ok: true, message: okMsg };
      } catch (e: unknown) {
        const errMsg = String((e as Error).message ?? e);
        const msg = stringT(cfg, 'saveFailed', 'Rules could not be saved: %s').replace('%s', errMsg);
        setSaveNotice(msg);
        return { ok: false, message: msg };
      } finally {
        setIsSaving(false);
      }
    },
    [cfg, markSaved],
  );

  return {
    savedSnapshot,
    setSavedSnapshot: markSaved,
    isSaving,
    saveNotice,
    setSaveNotice,
    hasUnsavedChanges,
    persistDocument,
  };
}
