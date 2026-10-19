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
  const TextControl = window.wp.components.TextControl;
  const CheckboxControl = window.wp.components.CheckboxControl;

  return (
    <div className="gro-action-form">
      <section className="gro-action-form__section">
        <h3 className="gro-action-form__heading">{t(cfg, 'ruleSection', 'Rule')}</h3>
        <div className="gro-action-form__body">
          <TextControl
            className="gro-editor-modal__label"
            label={t(cfg, 'placeholderLabel', 'Label (optional)')}
            value={e.label}
            onChange={(label: string) => onChange({ ...e, label })}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <div className="gro-rule-fields">
            <TextControl
              label={t(cfg, 'priorityLabel', 'Priority')}
              type="number"
              value={String(e.priority)}
              onChange={(value: string) => onChange({ ...e, priority: parseInt(value, 10) || 10 })}
              __nextHasNoMarginBottom
              __next40pxDefaultSize
            />
            <TextControl
              label={t(cfg, 'ruleIdShort', 'id')}
              value={e.id}
              onChange={(id: string) => onChange({ ...e, id })}
              __nextHasNoMarginBottom
              __next40pxDefaultSize
            />
          </div>
          <CheckboxControl
            className="gro-editor-modal__enabled"
            label={t(cfg, 'enabledLabel', 'Enabled')}
            checked={e.enabled}
            onChange={(enabled: boolean) => onChange({ ...e, enabled })}
            __nextHasNoMarginBottom
          />
          <div className="gro-editor-modal__toolbar">
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
        </div>
      </section>
      <TriggersBlock ruleIndex={ruleIndex} editable={e.trigger} onChange={(trigger) => onChange({ ...e, trigger })} />
      <ConditionsBlock ruleIndex={ruleIndex} editable={e} onChange={onChange} />
      <ActionsBlock ruleIndex={ruleIndex} editable={e} onChange={onChange} />
    </div>
  );
}
