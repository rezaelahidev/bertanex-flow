import {
  defaultValueJsonForField,
  fieldHasPresetValues,
  findConditionField,
  findMatchingValueChoice,
  opsNeedValue,
  valueJsonForChoice,
  type ConditionRow,
  type SuggestedConditionField,
} from '../ruleModel';
import { t, useCfg } from './ConfigContext';
import React, { useEffect, useMemo, useState } from 'react';

export interface ConditionValueFieldProps {
  row: ConditionRow;
  suggestedFields: readonly SuggestedConditionField[];
  onRowChange: (next: ConditionRow) => void;
}

export function ConditionValueField({ row, suggestedFields, onRowChange }: ConditionValueFieldProps): React.ReactElement | null {
  const cfg = useCfg();

  const fieldDef = useMemo(() => findConditionField(suggestedFields, row.field), [row.field, suggestedFields]);
  const usePresets = fieldHasPresetValues(fieldDef, row.op);
  const matchedChoice = useMemo(
    () => (usePresets ? findMatchingValueChoice(fieldDef, row.valueJson) : undefined),
    [fieldDef, row.valueJson, usePresets],
  );
  const [customMode, setCustomMode] = useState(() => usePresets && row.valueJson.trim() !== '' && !matchedChoice);

  useEffect(() => {
    if (!usePresets) {
      setCustomMode(false);
      return;
    }
    if (row.valueJson.trim() === '') {
      setCustomMode(false);
      return;
    }
    setCustomMode(!findMatchingValueChoice(fieldDef, row.valueJson));
  }, [fieldDef, row.field, row.op, row.valueJson, usePresets]);

  if (!opsNeedValue(row.op)) return null;

  if (!usePresets) {
    return (
      <div className="gro-nested-field gro-nested-field--full">
        <label>
          <strong>{t(cfg, 'valueCol', 'Value (JSON)')}</strong>
          <textarea
            rows={3}
            value={row.valueJson}
            placeholder={t(cfg, 'valueJsonPlaceholder', 'Examples: true, false, 3, "text", []')}
            onChange={(ev) => onRowChange({ ...row, valueJson: ev.target.value })}
          />
        </label>
      </div>
    );
  }

  const choices = fieldDef?.values ?? [];
  const selectValue = customMode ? '__custom__' : matchedChoice ? valueJsonForChoice(matchedChoice) : '';

  return (
    <div className="gro-nested-field gro-nested-field--full">
      <label>
        <strong>{t(cfg, 'valueColShort', 'Value')}</strong>
        <select
          value={selectValue}
          onChange={(ev) => {
            const v = ev.target.value;
            if (v === '__custom__') {
              setCustomMode(true);
              return;
            }
            setCustomMode(false);
            onRowChange({ ...row, valueJson: v });
          }}
        >
          <option value="" disabled>
            {t(cfg, 'valueSelectPlaceholder', 'Select a value…')}
          </option>
          {choices.map((choice) => (
            <option key={valueJsonForChoice(choice)} value={valueJsonForChoice(choice)}>
              {choice.label}
            </option>
          ))}
          <option value="__custom__">{t(cfg, 'customValue', 'Custom value (JSON)…')}</option>
        </select>
      </label>
      {customMode ? (
        <textarea
          rows={3}
          className="gro-condition-custom-value"
          value={row.valueJson}
          placeholder={t(cfg, 'valueJsonPlaceholder', 'Examples: true, false, 3, "text", []')}
          onChange={(ev) => onRowChange({ ...row, valueJson: ev.target.value })}
        />
      ) : null}
    </div>
  );
}

/** Apply default preset when field or operator changes. */
export function applyFieldDefaults(
  row: ConditionRow,
  suggestedFields: readonly SuggestedConditionField[],
  prevField?: string,
): ConditionRow {
  const fieldDef = findConditionField(suggestedFields, row.field);
  if (!fieldHasPresetValues(fieldDef, row.op)) {
    if (prevField !== undefined && prevField !== row.field) {
      return { ...row, valueJson: '' };
    }
    return row;
  }
  if (prevField !== undefined && prevField !== row.field) {
    return { ...row, valueJson: defaultValueJsonForField(fieldDef) };
  }
  if (!row.valueJson.trim()) {
    return { ...row, valueJson: defaultValueJsonForField(fieldDef) };
  }
  if (!findMatchingValueChoice(fieldDef, row.valueJson)) {
    return row;
  }
  return row;
}
