import { defaultDelaySchedule, formatDelaySummary } from '../delaySchedule';
import { slugLabel, type ActionRowUi, type EditableRule, type SmsTemplateChoice } from '../ruleModel';
import { ActionDelayField } from './ActionDelayField';
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
  const delayShown = formatDelaySummary(a.delaySchedule ?? defaultDelaySchedule(), a.delay_seconds, cfg);
  const numHuman = idx + 1;
  const openAria = openActionModalAria(cfg, numHuman, `${typeLabel} · ${templateShown}`);
  const removeAria = removeActionItemAria(cfg, numHuman, typeLabel);

  const renderTemplateField = (row: ActionRowUi, onRowChange: (next: ActionRowUi) => void): React.ReactElement => {
    const showPicker = usesSmsTemplate(row.type) && smsTemplates.length > 0;
    const selected = smsTemplates.find((x) => x.id === row.template_id);
    const previewBody = selected?.body ?? '';

    const TextControl = window.wp.components.TextControl;
    const SelectControl = window.wp.components.SelectControl;
    const Button = window.wp.components.Button;

    if (!showPicker) {
      return (
        <TextControl
          label={t(cfg, 'templateId', 'template_id')}
          value={row.template_id}
          onChange={(template_id: string) => onRowChange({ ...row, template_id })}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
      );
    }

    if (customTemplateId) {
      return (
        <>
          <TextControl
            label={t(cfg, 'templateId', 'template_id')}
            value={row.template_id}
            placeholder={t(cfg, 'templateCustom', 'Custom template ID…')}
            onChange={(template_id: string) => onRowChange({ ...row, template_id })}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <Button variant="tertiary" onClick={() => setCustomTemplateId(false)}>
            {t(cfg, 'templateSelect', 'SMS template')}
          </Button>
        </>
      );
    }

    const orphanId = row.template_id.trim();
    const orphanInList = orphanId !== '' && !smsTemplates.some((x) => x.id === orphanId);

    return (
      <>
        <SelectControl
          label={t(cfg, 'templateSelect', 'SMS template')}
          value={orphanInList ? orphanId : row.template_id}
          options={[
            { label: t(cfg, 'valueSelectPlaceholder', 'Select a value…'), value: '' },
            ...smsTemplates.map((tpl) => ({ label: `${tpl.label} (${tpl.id})`, value: tpl.id })),
            ...(orphanInList ? [{ label: orphanId, value: orphanId }] : []),
            { label: t(cfg, 'templateCustom', 'Custom template ID…'), value: '__custom__' },
          ]}
          onChange={(value: string) => {
            if (value === '__custom__') {
              setCustomTemplateId(true);
              onRowChange({ ...row, template_id: '' });
              return;
            }
            onRowChange({ ...row, template_id: value });
          }}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        {previewBody ? (
          <div className="gro-action-preview">
            <span className="gro-action-preview__label">{t(cfg, 'templatePreview', 'Preview:')}</span>
            <p className="gro-action-preview__text">{previewBody}</p>
          </div>
        ) : null}
        <Button className="gro-action-form__link" variant="link" onClick={openPatternsTab}>
          {t(cfg, 'templateConfigHint', 'Edit message text under Message patterns.')}
        </Button>
      </>
    );
  };

  const renderForm = (row: ActionRowUi, onRowChange: (next: ActionRowUi) => void): React.ReactElement => (
    <div className="gro-action-form">
      <section className="gro-action-form__section">
        <h3 className="gro-action-form__heading">{t(cfg, 'actionsHeading', 'Actions')}</h3>
        <div className="gro-action-form__body">
          {React.createElement(window.wp.components.SelectControl, {
            label: t(cfg, 'actionType', 'Type'),
            value: at.includes(row.type) ? row.type : at[0],
            options: at.map((x) => ({ label: slugLabel(cfg.actionTypeLabels, x), value: x })),
            onChange: (type: string) => onRowChange({ ...row, type }),
            __nextHasNoMarginBottom: true,
            __next40pxDefaultSize: true,
          })}
          {renderTemplateField(row, onRowChange)}
        </div>
      </section>
      <section className="gro-action-form__section">
        <h3 className="gro-action-form__heading">{t(cfg, 'delayLabel', 'Delay')}</h3>
        <div className="gro-action-form__body">
          <ActionDelayField
            schedule={row.delaySchedule ?? defaultDelaySchedule()}
            onChange={(schedule, delaySeconds) =>
              onRowChange({ ...row, delaySchedule: schedule, delay_seconds: delaySeconds })
            }
          />
        </div>
      </section>
      <section className="gro-action-form__section">
        <h3 className="gro-action-form__heading">{t(cfg, 'paramsJson', 'params (JSON object)')}</h3>
        <div className="gro-action-form__body">
          {React.createElement(window.wp.components.TextareaControl, {
            label: t(cfg, 'paramsJson', 'params (JSON object)'),
            hideLabelFromVision: true,
            rows: 5,
            value: row.paramsJson,
            onChange: (paramsJson: string) => onRowChange({ ...row, paramsJson }),
            __nextHasNoMarginBottom: true,
          })}
        </div>
      </section>
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
            <span className="gro-item-card__meta-key">{t(cfg, 'delayLabel', 'Delay')}</span>{' '}
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
              actions: next.length
                ? next
                : [{ type: at[0], template_id: '', delay_seconds: 0, delaySchedule: defaultDelaySchedule(), paramsJson: '{}' }],
            });
          }}
        />
      </article>
      <Modal
        title={`${t(cfg, 'actionsHeading', 'Actions')} #${numHuman}`}
        isOpen={isOpen}
        onClose={applyAndClose}
      >
        {draft ? renderForm(draft, setDraft) : null}
      </Modal>
    </>
  );
}
