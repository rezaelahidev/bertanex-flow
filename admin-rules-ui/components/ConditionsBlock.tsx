import type { EditableRule } from '../ruleModel';
import { ConditionItem } from './ConditionItem';
import { GroButton } from './GroButton';
import { GroIcon } from './GroIcon';
import { gridColsClass, t, useCfg, usePanelConfig } from './ConfigContext';
import React from 'react';

export interface ConditionsBlockProps {
  ruleIndex: number;
  editable: EditableRule;
  onChange: (next: EditableRule) => void;
}

export function ConditionsBlock({ ruleIndex, editable: e, onChange }: ConditionsBlockProps): React.ReactElement {
  const cfg = useCfg();
  const panel = usePanelConfig();
  const canAdd = e.conditions.length < panel.maxConditions;

  return (
    <div className="gro-rule-section">
      <h4 className="gro-section-title gro-rule-section__title">
        <GroIcon name="condition" />
        <span>{t(cfg, 'conditionsHeading', 'Conditions (all must match)')}</span>
      </h4>
      <div className={gridColsClass('gro-items-grid', panel.displayColumns)} role="list">
        {e.conditions.map((c, idx) => (
          <div key={idx} className="gro-items-grid__item" role="listitem">
            <ConditionItem ruleIndex={ruleIndex} idx={idx} row={c} editable={e} onChange={onChange} />
          </div>
        ))}
      </div>
      {canAdd ? (
        <GroButton
          size="sm"
          icon="add"
          className="gro-nested-list-add-button"
          onClick={() => onChange({ ...e, conditions: [...e.conditions, { field: '', op: 'eq', valueJson: '', pattern: '' }] })}
        >
          {t(cfg, 'addCondition', 'Add condition')}
        </GroButton>
      ) : (
        <p className="description gro-list-limit-hint">
          {t(cfg, 'conditionsLimitReached', 'Maximum number of conditions reached (%s).').replace(
            '%s',
            String(panel.maxConditions),
          )}
        </p>
      )}
    </div>
  );
}
