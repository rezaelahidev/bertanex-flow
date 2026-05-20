import type { EditableRule } from '../ruleModel';
import { GroButton } from './GroButton';
import { GroIcon } from './GroIcon';
import { Modal } from './Modal';
import { openRuleModalAria, removeCollapsedAria, t, useCfg } from './ConfigContext';
import { SimpleRuleForm } from './SimpleRuleForm';
import React, { useCallback, useState } from 'react';

function cloneEditableRule(source: EditableRule): EditableRule {
  return {
    ...source,
    trigger: {
      ...source.trigger,
      events: [...source.trigger.events],
      schedule: { ...source.trigger.schedule },
    },
    conditions: source.conditions.map((c) => ({ ...c })),
    actions: source.actions.map((a) => ({ ...a })),
  };
}

export interface SimpleRuleCardProps {
  ruleIndex: number;
  editable: EditableRule;
  onChange: (next: EditableRule) => void;
  onDuplicate: () => void;
  onMove: (delta: number) => void;
  onRemove: () => void;
  onSave: () => Promise<boolean>;
  isSaving: boolean;
  hasDocumentChanges: boolean;
}

export function SimpleRuleCard({
  ruleIndex,
  editable: e,
  onChange,
  onDuplicate,
  onMove,
  onRemove,
  onSave,
  isSaving,
  hasDocumentChanges,
}: SimpleRuleCardProps): React.ReactElement {
  const cfg = useCfg();
  const [isOpen, setIsOpen] = useState(false);
  const [draft, setDraft] = useState<EditableRule | null>(null);
  const [localDirty, setLocalDirty] = useState(false);

  const displayLabel = e.label.trim() || t(cfg, 'untitledRuleSummary', 'Untitled rule');
  const ruleNumHuman = ruleIndex + 1;
  const openAria = openRuleModalAria(cfg, ruleNumHuman, displayLabel);
  const removeAria = removeCollapsedAria(cfg, ruleNumHuman, displayLabel);
  const modalTitle = draft?.label.trim() || displayLabel;

  const commitDraft = useCallback(
    (next: EditableRule): void => {
      onChange(next);
    },
    [onChange],
  );

  const openModal = (): void => {
    setDraft(cloneEditableRule(e));
    setLocalDirty(false);
    setIsOpen(true);
  };

  const closeModal = useCallback((): void => {
    if (localDirty && draft) commitDraft(draft);
    setIsOpen(false);
    setDraft(null);
    setLocalDirty(false);
  }, [commitDraft, draft, localDirty]);

  const handleChange = (next: EditableRule): void => {
    setLocalDirty(true);
    setDraft(next);
  };

  const handleSave = async (): Promise<void> => {
    if (draft) commitDraft(draft);
    const ok = await onSave();
    if (ok) {
      setLocalDirty(false);
      closeModal();
    }
  };

  return (
    <>
      <article className="gro-rule-row gro-rule-row--simple">
        <button type="button" className="gro-rule-row__open" aria-label={openAria} onClick={openModal}>
          <span className="gro-rule-row__cell gro-rule-row__cell--label">
            <span className="gro-rule-row__label">{t(cfg, 'labelCol', 'Label')}</span>
            <strong>{displayLabel}</strong>
          </span>
          <span className="gro-rule-row__cell gro-rule-row__cell--priority">
            <span className="gro-rule-row__label">{t(cfg, 'priorityLabel', 'Priority')}</span>
            <strong>{e.priority}</strong>
          </span>
          <span className="gro-rule-row__cell gro-rule-row__cell--status">
            <span className="gro-rule-row__label">{t(cfg, 'statusLabel', 'Status')}</span>
            <span
              className={
                e.enabled ? 'gro-rule-card__status-badge gro-rule-card__status-badge--on' : 'gro-rule-card__status-badge gro-rule-card__status-badge--off'
              }
            >
              {e.enabled ? t(cfg, 'enabledLabel', 'Enabled') : t(cfg, 'disabledLabel', 'Disabled')}
            </span>
          </span>
          <span className="gro-rule-row__edit-hint" aria-hidden>
            <GroIcon name="edit" />
          </span>
        </button>
        <GroButton
          icon="delete"
          variant="danger"
          size="sm"
          iconOnly
          className="gro-rule-row__delete"
          aria-label={removeAria}
          onClick={() => {
            if (!window.confirm(t(cfg, 'confirmRemove', 'Remove this rule?'))) return;
            onRemove();
          }}
        />
      </article>
      <Modal
        title={`${t(cfg, 'ruleDetailsHeading', 'Edit rule')} — ${modalTitle}`}
        isOpen={isOpen}
        onClose={closeModal}
        footer={
          <>
            <GroButton variant="ghost" size="md" icon="close" onClick={closeModal}>
              {t(cfg, 'modalClose', 'Close')}
            </GroButton>
            <GroButton
              variant="primary"
              size="md"
              icon="save"
              disabled={isSaving || (!localDirty && !hasDocumentChanges)}
              onClick={() => void handleSave()}
            >
              {isSaving ? t(cfg, 'saving', 'Saving…') : t(cfg, 'saveRule', 'Save rule')}
            </GroButton>
          </>
        }
      >
        {draft ? (
          <SimpleRuleForm
            ruleIndex={ruleIndex}
            editable={draft}
            onChange={handleChange}
            onDuplicate={() => {
              onDuplicate();
              closeModal();
            }}
            onMove={onMove}
            onRemove={() => {
              onRemove();
              closeModal();
            }}
          />
        ) : null}
      </Modal>
    </>
  );
}
