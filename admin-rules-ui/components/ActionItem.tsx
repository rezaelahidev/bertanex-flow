import { slugLabel, type ActionRowUi, type EditableRule, type SmsTemplateChoice } from '../ruleModel';
import { GroButton } from './GroButton';
import { GroIcon } from './GroIcon';
import { Modal } from './Modal';
import { openActionModalAria, removeActionItemAria, t, useCfg } from './ConfigContext';
import React, { useCallback, useState } from 'react';

export interface ActionItemProps {
  ruleIndex: number;
  idx: number;
  row: ActionRowUi;
  editable: EditableRule;
  onChange: (next: EditableRule) => void;
}

function usesSmsTemplate(type: string): boolean {
  return type === 'send_sms' || type === 'send_discount';
}

function templateLabel(templates: SmsTemplateChoice[], templateId: string): string {
  const id = templateId.trim();
  if (id === '') return '—';
  const hit = templates.find((x) => x.id === id);
  return hit ? hit.label : id;
}

function openPatternsTab(): void {
  document.getElementById('gro-rules-tab-patterns')?.click();
}

export function ActionItem({ ruleIndex, idx, row: a, editable: e, onChange }: ActionItemProps): React.ReactElement {
  const cfg = useCfg();
  const [isOpen, setIsOpen] = useState(false);
  const [draft, setDraft] = useState<ActionRowUi | null>(null);
  const [customTemplateId, setCustomTemplateId] = useState(false);
  const at = cfg.actionTypes ?? ['log'];
  const smsTemplates = cfg.smsTemplates ?? [];

  const commitRow = useCallback(
    (row: ActionRowUi): void => {
      onChange({ ...e, actions: e.actions.map((x, i) => (i === idx ? row : x)) });
    },
    [e, idx, onChange],
  );

  const openModal = (): void => {
    setDraft({ ...a });
    const known = smsTemplates.some((x) => x.id === (a.template_id || '').trim());
    setCustomTemplateId((a.template_id || '').trim() !== '' && !known);
    setIsOpen(true);
  };

  const closeModal = useCallback((): void => {
    setIsOpen(false);
    setDraft(null);
    setCustomTemplateId(false);
  }, []);

  const applyAndClose = useCallback((): void => {
    if (draft) commitRow(draft);
    closeModal();
  }, [closeModal, commitRow, draft]);

  const typeLabel = slugLabel(cfg.actionTypeLabels, at.includes(a.type) ? a.type : at[0]);
  const templateShown = templateLabel(smsTemplates, a.template_id);
  const delayShown = `${String(a.delay_seconds)}s`;
  const numHuman = idx + 1;
  const openAria = openActionModalAria(cfg, numHuman, `${typeLabel} · ${templateShown}`);
  const removeAria = removeActionItemAria(cfg, numHuman, typeLabel);

  const renderTemplateField = (row: ActionRowUi, onRowChange: (next: ActionRowUi) => void): React.ReactElement => {
    const showPicker = usesSmsTemplate(row.type) && smsTemplates.length > 0;
    const selected = smsTemplates.find((x) => x.id === row.template_id);
    const previewBody = selected?.body ?? '';

    if (!showPicker) {
      return (
        <p className="gro-action-field gro-action-field--template">
          <label>
            <strong>{t(cfg, 'templateId', 'template_id')}</strong>
            <input
              type="text"
              className="regular-text code"
              value={row.template_id}
              onChange={(ev) => onRowChange({ ...row, template_id: ev.target.value })}
            />
          </label>
        </p>
      );
    }

    if (customTemplateId) {
      return (
        <p className="gro-action-field gro-action-field--template">
          <label>
            <strong>{t(cfg, 'templateId', 'template_id')}</strong>
            <input
              type="text"
              className="regular-text code"
              value={row.template_id}
              onChange={(ev) => onRowChange({ ...row, template_id: ev.target.value })}
              placeholder={t(cfg, 'templateCustom', 'Custom template ID…')}
            />
          </label>
          <button type="button" className="button-link gro-action-template-back" onClick={() => setCustomTemplateId(false)}>
            {t(cfg, 'templateSelect', 'SMS template')}
          </button>
        </p>
      );
    }

    const orphanId = row.template_id.trim();
    const orphanInList = orphanId !== '' && !smsTemplates.some((x) => x.id === orphanId);

    return (
      <p className="gro-action-field gro-action-field--template gro-action-field--template-picker">
        <label>
          <strong>{t(cfg, 'templateSelect', 'SMS template')}</strong>
          <select
            value={orphanInList ? orphanId : row.template_id}
            onChange={(ev) => {
              const v = ev.target.value;
              if (v === '__custom__') {
                setCustomTemplateId(true);
                onRowChange({ ...row, template_id: '' });
                return;
              }
              onRowChange({ ...row, template_id: v });
            }}
          >
            <option value="">{t(cfg, 'valueSelectPlaceholder', 'Select a value…')}</option>
            {smsTemplates.map((tpl) => (
              <option key={tpl.id} value={tpl.id}>
                {tpl.label} ({tpl.id})
              </option>
            ))}
            {orphanInList ? (
              <option value={orphanId}>
                {orphanId}
              </option>
            ) : null}
            <option value="__custom__">{t(cfg, 'templateCustom', 'Custom template ID…')}</option>
          </select>
        </label>
        {previewBody ? (
          <p className="description gro-action-template-preview">
            <span className="gro-action-template-preview__label">{t(cfg, 'templatePreview', 'Preview:')}</span>{' '}
            {previewBody}
          </p>
        ) : null}
        <p className="description">
          <button type="button" className="button-link" onClick={openPatternsTab}>
            {t(cfg, 'templateConfigHint', 'Edit message text under Message patterns.')}
          </button>
        </p>
      </p>
    );
  };

  const renderForm = (row: ActionRowUi, onRowChange: (next: ActionRowUi) => void): React.ReactElement => (
    <div className="gro-action-row gro-action-row--nested">
      <div className="gro-action-row__grid">
        <p className="gro-action-field gro-action-field--type">
          <label>
            <strong>{t(cfg, 'actionType', 'Type')}</strong>
            <select value={at.includes(row.type) ? row.type : at[0]} onChange={(ev) => onRowChange({ ...row, type: ev.target.value })}>
              {at.map((x) => (
                <option key={x} value={x}>
                  {slugLabel(cfg.actionTypeLabels, x)}
                </option>
              ))}
            </select>
          </label>
        </p>
        {renderTemplateField(row, onRowChange)}
        <p className="gro-action-field gro-action-field--delay">
          <label>
            <strong>{t(cfg, 'delaySeconds', 'delay_seconds')}</strong>
            <input
              type="number"
              min={0}
              step={1}
              inputMode="numeric"
              value={row.delay_seconds}
              onChange={(ev) => onRowChange({ ...row, delay_seconds: parseInt(ev.target.value, 10) || 0 })}
            />
          </label>
        </p>
      </div>
      <p className="gro-action-row__params">
        <label>
          <strong>{t(cfg, 'paramsJson', 'params (JSON object)')}</strong>
          <textarea
            rows={4}
            className="large-text code"
            value={row.paramsJson}
            onChange={(ev) => onRowChange({ ...row, paramsJson: ev.target.value })}
          />
        </label>
      </p>
    </div>
  );

  return (
    <>
      <article className="gro-item-card gro-item-card--closed">
        <button type="button" className="gro-item-card__open" aria-label={openAria} onClick={openModal}>
          <span className="gro-item-card__index" aria-hidden>
            #{numHuman}
          </span>
          <span className="gro-item-card__field">{typeLabel}</span>
          <span className="gro-item-card__meta">
            {usesSmsTemplate(a.type) ? (
              <>
                <span className="gro-item-card__meta-key">{t(cfg, 'templateSelect', 'SMS template')}</span>{' '}
                <strong>{templateShown}</strong>
              </>
            ) : (
              <code>{(a.template_id || '').trim() || '—'}</code>
            )}
            <span className="gro-item-card__sep" aria-hidden>
              {' · '}
            </span>
            <span className="gro-item-card__meta-key">{t(cfg, 'delaySeconds', 'delay_seconds')}</span>{' '}
            <strong>{delayShown}</strong>
          </span>
          <span className="gro-item-card__edit-hint" aria-hidden>
            <GroIcon name="edit" />
          </span>
        </button>
        <GroButton
          icon="delete"
          variant="danger"
          size="sm"
          iconOnly
          className="gro-item-card__delete"
          aria-label={removeAria}
          onClick={() => {
            const next = e.actions.filter((_, i) => i !== idx);
            onChange({
              ...e,
              actions: next.length ? next : [{ type: at[0], template_id: '', delay_seconds: 0, paramsJson: '{}' }],
            });
          }}
        />
      </article>
      <Modal
        title={`${t(cfg, 'actionsHeading', 'Actions')} #${numHuman}`}
        isOpen={isOpen}
        onClose={applyAndClose}
        footer={
          <GroButton variant="primary" size="md" icon="save" onClick={applyAndClose}>
            {t(cfg, 'modalClose', 'Close')}
          </GroButton>
        }
      >
        {draft ? renderForm(draft, setDraft) : null}
      </Modal>
    </>
  );
}
