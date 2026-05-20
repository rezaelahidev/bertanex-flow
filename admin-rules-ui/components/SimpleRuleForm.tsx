import type { EditableRule } from '../ruleModel';
import { ActionsBlock } from './ActionsBlock';
import { ConditionsBlock } from './ConditionsBlock';
import { GroButton } from './GroButton';
import { t, useCfg } from './ConfigContext';
import { TriggersBlock } from './TriggersBlock';
import React from 'react';

export interface SimpleRuleFormProps {
  ruleIndex: number;
  editable: EditableRule;
  onChange: (next: EditableRule) => void;
  onDuplicate: () => void;
  onMove: (delta: number) => void;
  onRemove: () => void;
}

export function SimpleRuleForm({
  ruleIndex,
  editable: e,
  onChange,
  onDuplicate,
  onMove,
  onRemove,
}: SimpleRuleFormProps): React.ReactElement {
  const cfg = useCfg();

  return (
    <>
      <header className="gro-rule-modal__head">
        <h4 className="gro-rule-modal__heading">{t(cfg, 'ruleDetailsHeading', 'Edit rule')}</h4>
        <input
          type="text"
          className="regular-text gro-rule-modal__label-input"
          placeholder={t(cfg, 'placeholderLabel', 'Label (optional)')}
          value={e.label}
          onChange={(ev) => onChange({ ...e, label: ev.target.value })}
        />
        <span className="gro-rule-card__meta">
          <label>
            <input type="checkbox" checked={e.enabled} onChange={(ev) => onChange({ ...e, enabled: ev.target.checked })} />{' '}
            {t(cfg, 'enabledLabel', 'Enabled')}
          </label>
          <label>
            {t(cfg, 'priorityLabel', 'Priority')}{' '}
            <input
              type="number"
              step={1}
              style={{ width: '5em' }}
              value={e.priority}
              onChange={(ev) => onChange({ ...e, priority: parseInt(ev.target.value, 10) || 10 })}
            />
          </label>
          <label>
            {t(cfg, 'ruleIdShort', 'id')}{' '}
            <input type="text" className="code" style={{ width: '16em' }} value={e.id} onChange={(ev) => onChange({ ...e, id: ev.target.value })} />
          </label>
        </span>
        <div className="gro-rule-card__actions-row">
          <GroButton size="sm" icon="duplicate" onClick={onDuplicate}>
            {t(cfg, 'dupRule', 'Duplicate')}
          </GroButton>
          <GroButton size="sm" icon="up" onClick={() => onMove(-1)}>
            {t(cfg, 'up', 'Move up')}
          </GroButton>
          <GroButton size="sm" icon="down" onClick={() => onMove(1)}>
            {t(cfg, 'down', 'Move down')}
          </GroButton>
          <GroButton size="sm" variant="danger" icon="delete" onClick={onRemove}>
            {t(cfg, 'removeRule', 'Remove')}
          </GroButton>
        </div>
      </header>
      <div className="gro-rule-modal__body">
        <TriggersBlock ruleIndex={ruleIndex} editable={e.trigger} onChange={(trigger) => onChange({ ...e, trigger })} />
        <ConditionsBlock ruleIndex={ruleIndex} editable={e} onChange={onChange} />
        <ActionsBlock ruleIndex={ruleIndex} editable={e} onChange={onChange} />
      </div>
    </>
  );
}
