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
  const TextareaControl = window.wp.components.TextareaControl;
  const SelectControl = window.wp.components.SelectControl;

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
      <TextareaControl
        label={t(cfg, 'valueCol', 'Value (JSON)')}
        rows={3}
        value={row.valueJson}
        placeholder={t(cfg, 'valueJsonPlaceholder', 'Examples: true, false, 3, "text", []')}
        onChange={(valueJson: string) => onRowChange({ ...row, valueJson })}
        __nextHasNoMarginBottom
      />
    );
  }

  const choices = fieldDef?.values ?? [];
  const selectValue = customMode ? '__custom__' : matchedChoice ? valueJsonForChoice(matchedChoice) : '';

  return (
    <>
      <SelectControl
        label={t(cfg, 'valueColShort', 'Value')}
        value={selectValue}
        options={[
          { label: t(cfg, 'valueSelectPlaceholder', 'Select a value…'), value: '', disabled: true },
          ...choices.map((choice) => ({ label: choice.label, value: valueJsonForChoice(choice) })),
          { label: t(cfg, 'customValue', 'Custom value (JSON)…'), value: '__custom__' },
        ]}
        onChange={(value: string) => {
          if (value === '__custom__') {
            setCustomMode(true);
            return;
          }
          setCustomMode(false);
          onRowChange({ ...row, valueJson: value });
        }}
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      {customMode ? (
        <TextareaControl
          label={t(cfg, 'customValue', 'Custom value (JSON)…')}
          rows={3}
          value={row.valueJson}
          placeholder={t(cfg, 'valueJsonPlaceholder', 'Examples: true, false, 3, "text", []')}
          onChange={(valueJson: string) => onRowChange({ ...row, valueJson })}
          __nextHasNoMarginBottom
        />
      ) : null}
    </>
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
