import type { EditableRule } from '../ruleModel';
import { ActionItem } from './ActionItem';
import { GroButton } from './GroButton';
import { GroIcon } from './GroIcon';
import { gridColsClass, t, useCfg, usePanelConfig } from './ConfigContext';
import React from 'react';

export interface ActionsBlockProps {
  ruleIndex: number;
  editable: EditableRule;
  onChange: (next: EditableRule) => void;
}

export function ActionsBlock({ ruleIndex, editable: e, onChange }: ActionsBlockProps): React.ReactElement {
  const cfg = useCfg();
  const panel = usePanelConfig();
  const at = cfg.actionTypes ?? ['log'];
  const canAdd = e.actions.length < panel.maxActions;

  return (
    <div className="gro-rule-section">
      <h4 className="gro-section-title gro-rule-section__title">
        <GroIcon name="action" />
        <span>{t(cfg, 'actionsHeading', 'Actions')}</span>
      </h4>
      <div className={gridColsClass('gro-items-grid', panel.displayColumns)} role="list">
        {e.actions.map((a, idx) => (
          <div key={idx} className="gro-items-grid__item" role="listitem">
            <ActionItem ruleIndex={ruleIndex} idx={idx} row={a} editable={e} onChange={onChange} />
          </div>
        ))}
      </div>
      {canAdd ? (
        <GroButton
          size="sm"
          icon="add"
          className="gro-nested-list-add-button"
          onClick={() =>
            onChange({
              ...e,
              actions: [...e.actions, { type: at[0], template_id: '', delay_seconds: 0, paramsJson: '{}' }],
            })
          }
        >
          {t(cfg, 'addAction', 'Add action')}
        </GroButton>
      ) : (
        <p className="description gro-list-limit-hint">
          {t(cfg, 'actionsLimitReached', 'Maximum number of actions reached (%s).').replace('%s', String(panel.maxActions))}
        </p>
      )}
    </div>
  );
}
