import { GroButton } from './GroButton';
import { GroIcon } from './GroIcon';
import { ConfirmDialog } from './ConfirmDialog';
import { Modal } from './Modal';
import { openRuleModalAria, removeCollapsedAria, t, useCfg } from './ConfigContext';
import React, { useState } from 'react';

export interface AdvancedRuleCardProps {
  ruleIndex: number;
  snapshot: Record<string, unknown>;
  onRemove: () => void;
}

export function AdvancedRuleCard({ ruleIndex, snapshot, onRemove }: AdvancedRuleCardProps): React.ReactElement {
  const cfg = useCfg();
  const [isOpen, setIsOpen] = useState(false);
  const [confirmRemove, setConfirmRemove] = useState(false);
  const ruleId = typeof snapshot.id === 'string' ? snapshot.id : `#${ruleIndex + 1}`;
  const ruleNumHuman = ruleIndex + 1;
  const summaryForAria = typeof snapshot.id === 'string' ? snapshot.id : `Rule ${ruleNumHuman}`;
  const openAria = openRuleModalAria(cfg, ruleNumHuman, summaryForAria);
  const removeAria = removeCollapsedAria(cfg, ruleNumHuman, summaryForAria);
  const enabled = typeof snapshot.enabled === 'boolean' ? snapshot.enabled : true;
  const priority =
    typeof snapshot.priority === 'number' ? snapshot.priority : parseInt(String(snapshot.priority ?? 10), 10) || 10;
  const label = typeof snapshot.label === 'string' ? snapshot.label : '';

  return (
    <>
      <article className="gro-rule-row gro-rule-row--advanced">
        <button type="button" className="gro-rule-row__open" aria-label={openAria} onClick={() => setIsOpen(true)}>
          <span className="gro-rule-row__cell gro-rule-row__cell--label">
            <span className="gro-rule-row__label">{t(cfg, 'labelCol', 'Label')}</span>
            <strong>{label.trim() || '—'}</strong>
          </span>
          <span className="gro-rule-row__cell gro-rule-row__cell--priority">
            <span className="gro-rule-row__label">{t(cfg, 'priorityLabel', 'Priority')}</span>
            <strong>{priority}</strong>
          </span>
          <span className="gro-rule-row__cell gro-rule-row__cell--status">
            <span className="gro-rule-row__label">{t(cfg, 'statusLabel', 'Status')}</span>
            <span className="gro-badge gro-badge--warn">{t(cfg, 'advancedBadge', 'Advanced')}</span>
            <span
              className={
                enabled ? 'gro-rule-card__status-badge gro-rule-card__status-badge--on' : 'gro-rule-card__status-badge gro-rule-card__status-badge--off'
              }
            >
              {enabled ? t(cfg, 'enabledLabel', 'Enabled') : t(cfg, 'disabledLabel', 'Disabled')}
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
          onClick={() => setConfirmRemove(true)}
        />
      </article>
      <Modal
        title={String(ruleId)}
        isOpen={isOpen}
        onClose={() => setIsOpen(false)}
      >
        {React.createElement(
          window.wp.components.Notice,
          { status: 'warning', isDismissible: false },
          t(
            cfg,
            'advancedDesc',
            'This rule uses triggers or nested conditions this editor cannot change. It stays read-only and runs as stored.',
          ),
        )}
        <div className="gro-editor-modal__toolbar">
          <GroButton
            size="sm"
            variant="danger"
            icon="delete"
            onClick={() => setConfirmRemove(true)}
          >
            {t(cfg, 'removeRule', 'Remove')}
          </GroButton>
        </div>
      </Modal>
      <ConfirmDialog
        isOpen={confirmRemove}
        message={t(cfg, 'confirmRemoveAdv', 'Remove this rule from the document?')}
        confirmLabel={t(cfg, 'removeRule', 'Remove')}
        onConfirm={() => {
          setConfirmRemove(false);
          setIsOpen(false);
          onRemove();
        }}
        onCancel={() => setConfirmRemove(false)}
      />
    </>
  );
}
