import {
  normalizeSuggestedConditionFields,
  stringT,
  type GroRulesEditorConfig,
  type RulesPanelConfig,
  type SmsTemplateChoice,
} from '../ruleModel';
import { createContext, useContext } from 'react';

export const CfgContext = createContext<GroRulesEditorConfig>({});

function normalizePanelConfig(raw: RulesPanelConfig | undefined): Required<RulesPanelConfig> {
  const maxConditions = Math.max(1, Math.min(50, parseInt(String(raw?.maxConditions ?? 1), 10) || 1));
  const maxActions = Math.max(1, Math.min(50, parseInt(String(raw?.maxActions ?? 1), 10) || 1));
  const displayColumns = parseInt(String(raw?.displayColumns ?? 1), 10) === 2 ? 2 : 1;
  return { maxConditions, maxActions, displayColumns };
}

export function normalizeConfig(raw: GroRulesEditorConfig | undefined): GroRulesEditorConfig {
  return {
    strings: {},
    ...raw,
    panelConfig: normalizePanelConfig(raw?.panelConfig),
    defaultVersion: raw?.defaultVersion ?? '1.0.0',
    recurrences: raw?.recurrences ?? ['hourly', 'twicedaily', 'daily', 'weekly'],
    operators: raw?.operators ?? ['eq'],
    operatorLabels: raw?.operatorLabels ?? {},
    actionTypes: raw?.actionTypes ?? ['send_sms'],
    actionTypeLabels: raw?.actionTypeLabels ?? {},
    recurrenceLabels: raw?.recurrenceLabels ?? {},
    suggestedFields: normalizeSuggestedConditionFields(raw?.suggestedFields),
    smsTemplates: normalizeSmsTemplates(raw?.smsTemplates),
    events: raw?.events ?? [],
  };
}

function normalizeSmsTemplates(raw: GroRulesEditorConfig['smsTemplates']): SmsTemplateChoice[] {
  if (!Array.isArray(raw)) return [];
  const out: SmsTemplateChoice[] = [];
  for (const item of raw) {
    if (!item || typeof item !== 'object') continue;
    const row = item as Record<string, unknown>;
    const id = typeof row.id === 'string' ? row.id.trim() : '';
    if (id === '') continue;
    out.push({
      id,
      label: typeof row.label === 'string' && row.label.trim() !== '' ? row.label.trim() : id,
      body: typeof row.body === 'string' ? row.body : '',
    });
  }
  return out;
}

export function t(cfg: GroRulesEditorConfig, key: string, fallback: string): string {
  return stringT(cfg, key, fallback);
}

export function useCfg(): GroRulesEditorConfig {
  return useContext(CfgContext);
}

export function usePanelConfig(): Required<RulesPanelConfig> {
  const cfg = useCfg();
  return cfg.panelConfig ?? normalizePanelConfig(undefined);
}

/** BEM-style modifier for 1- or 2-column grids (`gro-rules-rule-list`, `gro-items-grid`, …). */
export function gridColsClass(baseClass: string, columns: number): string {
  const cols = columns === 2 ? 2 : 1;
  return `${baseClass} ${baseClass}--cols-${String(cols)}`;
}

export function openRuleModalAria(cfg: GroRulesEditorConfig, ruleNum: number, title: string): string {
  return stringT(cfg, 'openRuleModal', 'Edit rule %s').replace('%s', `#${String(ruleNum)}: ${title}`);
}

export function removeCollapsedAria(cfg: GroRulesEditorConfig, ruleNum: number, ruleTitle: string): string {
  return stringT(cfg, 'removeRuleCollapsedAria', 'Remove %s permanently from this list').replace(
    '%s',
    `rule #${String(ruleNum)}: ${ruleTitle}`,
  );
}

export function openConditionModalAria(cfg: GroRulesEditorConfig, num: number, summary: string): string {
  return stringT(cfg, 'openConditionModal', 'Edit condition %s').replace('%s', `#${String(num)}: ${summary}`);
}

export function openActionModalAria(cfg: GroRulesEditorConfig, num: number, summary: string): string {
  return stringT(cfg, 'openActionModal', 'Edit action %s').replace('%s', `#${String(num)}: ${summary}`);
}

export function removeConditionItemAria(cfg: GroRulesEditorConfig, num: number, summary: string): string {
  return stringT(cfg, 'removeConditionAria', 'Remove condition %s from this rule').replace('%s', `#${String(num)}: ${summary}`);
}

export function removeActionItemAria(cfg: GroRulesEditorConfig, num: number, summary: string): string {
  return stringT(cfg, 'removeActionAria', 'Remove action %s from this rule').replace('%s', `#${String(num)}: ${summary}`);
}
