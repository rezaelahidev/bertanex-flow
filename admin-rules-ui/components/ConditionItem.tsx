import {
  conditionFieldLabel,
  conditionValueLabel,
  operatorOptions,
  slugLabel,
  type ConditionRow,
  type EditableRule,
  type SuggestedConditionField,
} from '../ruleModel';
import { applyFieldDefaults, ConditionValueField } from './ConditionValueField';
import { GroButton } from './GroButton';
import { GroIcon } from './GroIcon';
import { Modal } from './Modal';
import { openConditionModalAria, removeConditionItemAria, t, useCfg } from './ConfigContext';
import React, { useCallback, useState } from 'react';

export interface ConditionItemProps {
  ruleIndex: number;
  idx: number;
  row: ConditionRow;
  editable: EditableRule;
  onChange: (next: EditableRule) => void;
}

export function ConditionItem({ ruleIndex, idx, row: c, editable: e, onChange }: ConditionItemProps): React.ReactElement {
  const cfg = useCfg();
  const [isOpen, setIsOpen] = useState(false);
  const [draft, setDraft] = useState<ConditionRow | null>(null);
  const suggested: readonly SuggestedConditionField[] = cfg.suggestedFields ?? [];
  const suggestedNames = suggested.map((f) => f.name);

  const commitRow = useCallback(
    (row: ConditionRow): void => {
      onChange({ ...e, conditions: e.conditions.map((x, i) => (i === idx ? row : x)) });
    },
    [e, idx, onChange],
  );

  const openModal = (): void => {
    setDraft({ ...c });
    setIsOpen(true);
  };

  const closeModal = useCallback((): void => {
    setIsOpen(false);
    setDraft(null);
  }, []);

  const applyAndClose = useCallback((): void => {
    if (draft) commitRow(draft);
    closeModal();
  }, [closeModal, commitRow, draft]);

  const fieldShown =
    conditionFieldLabel(suggested, c.field) || (c.field || '').trim() || t(cfg, 'fieldPlaceholder', 'State field key');
  const opShown = slugLabel(cfg.operatorLabels, c.op);
  const valueShown = conditionValueLabel(suggested, c.field, c.valueJson);
  const numHuman = idx + 1;
  const summaryParts = [fieldShown, opShown, valueShown].filter(Boolean);
  const openAria = openConditionModalAria(cfg, numHuman, summaryParts.join(' · '));
  const removeAria = removeConditionItemAria(cfg, numHuman, fieldShown);

  const renderForm = (row: ConditionRow, onRowChange: (next: ConditionRow) => void): React.ReactElement => {
    const match = suggestedNames.includes(row.field);
    const fieldSelValue = match ? row.field : '__custom__';

    const SelectControl = window.wp.components.SelectControl;
    const TextControl = window.wp.components.TextControl;

    return (
      <div className="gro-action-form">
        <div className="gro-action-form__body gro-condition-item-fields">
        <SelectControl
          label={t(cfg, 'fieldCol', 'Field')}
          value={fieldSelValue}
          options={[
            { label: t(cfg, 'customField', 'Custom…'), value: '__custom__' },
            ...suggested.map((sf) => ({ label: sf.label, value: sf.name })),
          ]}
          onChange={(value: string) => {
            const prevField = row.field;
            if (value === '__custom__') {
              onRowChange(applyFieldDefaults({ ...row, field: match ? '' : row.field }, suggested, prevField));
              return;
            }
            onRowChange(applyFieldDefaults({ ...row, field: value }, suggested, prevField));
          }}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        {fieldSelValue === '__custom__' ? (
          <TextControl
            label={t(cfg, 'fieldPlaceholder', 'State field key')}
            value={match ? '' : row.field}
            onChange={(field: string) => onRowChange({ ...row, field })}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
        ) : null}
        <SelectControl
          label={t(cfg, 'opCol', 'Operator')}
          value={row.op}
          options={operatorOptions(cfg, row.op).map((o) => ({ label: slugLabel(cfg.operatorLabels, o), value: o }))}
          onChange={(op: string) => onRowChange(applyFieldDefaults({ ...row, op }, suggested, row.field))}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        {row.op === 'regex' ? (
          <TextControl
            label={t(cfg, 'patternCol', 'Pattern (regex)')}
            value={row.pattern}
            placeholder={t(cfg, 'regexPatternPlaceholder', '/^...$/')}
            onChange={(pattern: string) => onRowChange({ ...row, pattern })}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
        ) : null}
        <ConditionValueField row={row} suggestedFields={suggested} onRowChange={onRowChange} />
        </div>
      </div>
    );
  };

  return (
    <>
      <article className="gro-item-card gro-item-card--closed">
        <button type="button" className="gro-item-card__open" aria-label={openAria} onClick={openModal}>
          <span className="gro-item-card__index" aria-hidden>
            #{numHuman}
          </span>
          <span className="gro-item-card__field gro-item-card__field--mono">{fieldShown}</span>
          <span className="gro-item-card__meta">
            <span className="gro-item-card__meta-key">{t(cfg, 'opCol', 'Operator')}</span>{' '}
            <strong>{opShown}</strong>
            {valueShown ? (
              <>
                <span className="gro-item-card__sep" aria-hidden>
                  {' · '}
                </span>
                <span className="gro-item-card__meta-key">{t(cfg, 'valueColShort', 'Value')}</span>{' '}
                <strong>{valueShown}</strong>
              </>
            ) : null}
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
            const next = e.conditions.filter((_, i) => i !== idx);
            onChange({
              ...e,
              conditions: next.length ? next : [{ field: '', op: 'eq', valueJson: '', pattern: '' }],
            });
          }}
        />
      </article>
      <Modal
        title={`${t(cfg, 'conditionsHeading', 'Conditions')} #${numHuman}`}
        isOpen={isOpen}
        onClose={applyAndClose}
      >
        {draft ? renderForm(draft, setDraft) : null}
      </Modal>
    </>
  );
}
