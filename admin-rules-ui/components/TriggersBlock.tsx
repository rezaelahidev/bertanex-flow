import { slugLabel, type EditableTrigger } from '../ruleModel';
import { GroIcon } from './GroIcon';
import { t, useCfg } from './ConfigContext';
import React, { useMemo } from 'react';

export interface TriggersBlockProps {
  ruleIndex: number;
  editable: EditableTrigger;
  onChange: (next: EditableTrigger) => void;
}

export function TriggersBlock({ ruleIndex, editable: te, onChange }: TriggersBlockProps): React.ReactElement {
  const cfg = useCfg();
  const radioGroup = `gro_tr_${ruleIndex}`;
  const eventOpts = cfg.events ?? [];

  const extras = useMemo(() => te.events.filter((x) => !eventOpts.some((eo) => eo.value === x)), [te.events, eventOpts]);

  const syncCustomExtras = (customLine: string): void => {
    const known = eventOpts.map((eo) => eo.value);
    const parts = customLine
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);
    const fromCheckboxes = te.events.filter((x) => known.includes(x));
    const merged = [...fromCheckboxes];
    parts.forEach((p) => {
      if (!merged.includes(p)) merged.push(p);
    });
    onChange({ ...te, events: merged });
  };

  return (
    <div className="gro-rule-section">
      <h4 className="gro-section-title gro-rule-section__title">
        <GroIcon name="trigger" />
        <span>{t(cfg, 'triggerHeading', 'When to run')}</span>
      </h4>
      <div className="gro-trigger-mode">
        {(
          [
            ['none', t(cfg, 'triggerNone', 'Evaluate without event filter')],
            ['events', t(cfg, 'triggerEvents', 'On automation events')],
            ['schedule', t(cfg, 'triggerSchedule', 'On a schedule (cron)')],
          ] as const
        ).map(([value, label]) => (
          <label key={value}>
            <input
              type="radio"
              name={radioGroup}
              checked={te.mode === value}
              onChange={() => {
                const rec = cfg.recurrences?.[0] ?? 'daily';
                onChange({
                  ...te,
                  mode: value,
                  schedule:
                    value === 'schedule' && (!te.schedule.hook || te.mode !== 'schedule')
                      ? { hook: '', recurrence: te.schedule.recurrence || rec }
                      : te.schedule,
                });
              }}
            />{' '}
            {label}
          </label>
        ))}
      </div>
      {te.mode === 'events' ? (
        <>
          <div className="gro-events-grid">
            {eventOpts.map((ev) => (
              <label key={ev.value}>
                <input
                  type="checkbox"
                  checked={te.events.includes(ev.value)}
                  onChange={(e) => {
                    let next = te.events.slice();
                    if (e.target.checked && !next.includes(ev.value)) next.push(ev.value);
                    if (!e.target.checked) next = next.filter((x) => x !== ev.value);
                    onChange({ ...te, events: next });
                  }}
                />{' '}
                {ev.label || ev.value}
              </label>
            ))}
          </div>
          <p>
            <label>
              <strong>{t(cfg, 'customEventsLabel', 'Additional event names (comma-separated)')}</strong>
            </label>
            <input
              type="text"
              className="large-text"
              defaultValue={extras.join(', ')}
              key={`ce-${extras.join('|')}-${ruleIndex}`}
              onBlur={(e) => syncCustomExtras(e.target.value)}
            />
          </p>
        </>
      ) : null}
      {te.mode === 'schedule' ? (
        <div className="gro-schedule-fields">
          <p>
            <label>
              <strong>{t(cfg, 'hookLabel', 'Cron hook')}</strong>
            </label>
            <input
              type="text"
              className="regular-text"
              value={te.schedule.hook}
              onChange={(e) => onChange({ ...te, schedule: { ...te.schedule, hook: e.target.value } })}
            />
          </p>
          <p>
            <label>
              <strong>{t(cfg, 'recurrenceLabel', 'Recurrence')}</strong>
            </label>
            <select
              value={te.schedule.recurrence}
              onChange={(e) => onChange({ ...te, schedule: { ...te.schedule, recurrence: e.target.value } })}
            >
              {(cfg.recurrences ?? []).map((r) => (
                <option key={r} value={r}>
                  {slugLabel(cfg.recurrenceLabels, r)}
                </option>
              ))}
            </select>
          </p>
        </div>
      ) : null}
    </div>
  );
}
