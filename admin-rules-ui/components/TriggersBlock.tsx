import { slugLabel, type EditableTrigger } from '../ruleModel';
import { GroButton } from './GroButton';
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
  const knownEventValues = useMemo(() => eventOpts.map((eo) => eo.value), [eventOpts]);
  const selectedKnownCount = useMemo(
    () => te.events.filter((value) => knownEventValues.includes(value)).length,
    [te.events, knownEventValues],
  );

  const toggleEvent = (value: string, checked: boolean): void => {
    let next = te.events.slice();
    if (checked && !next.includes(value)) next.push(value);
    if (!checked) next = next.filter((x) => x !== value);
    onChange({ ...te, events: next });
  };

  const selectAllEvents = (): void => {
    const merged = [...te.events];
    knownEventValues.forEach((value) => {
      if (!merged.includes(value)) merged.push(value);
    });
    onChange({ ...te, events: merged });
  };

  const clearKnownEvents = (): void => {
    onChange({ ...te, events: te.events.filter((value) => !knownEventValues.includes(value)) });
  };

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
          <div className="gro-events-picker">
            {eventOpts.length > 0 ? (
              <div className="gro-events-picker__toolbar">
                <p className="gro-events-picker__summary">
                  <span className="gro-events-picker__count">
                    {t(cfg, 'eventsSelectedCount', '%1$s of %2$s events selected')
                      .replace('%1$s', String(selectedKnownCount))
                      .replace('%2$s', String(eventOpts.length))}
                  </span>
                  {selectedKnownCount > 0 ? (
                    <span className="gro-events-picker__hint">
                      {t(cfg, 'eventsSelectedHint', 'Rule runs when any selected event fires.')}
                    </span>
                  ) : (
                    <span className="gro-events-picker__hint gro-events-picker__hint--warn">
                      {t(cfg, 'eventsNoneSelectedHint', 'Select at least one event or add custom names below.')}
                    </span>
                  )}
                </p>
                <div className="gro-events-picker__actions">
                  <GroButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={selectedKnownCount === eventOpts.length}
                    onClick={selectAllEvents}
                  >
                    {t(cfg, 'selectAllEvents', 'Select all')}
                  </GroButton>
                  <GroButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={selectedKnownCount === 0}
                    onClick={clearKnownEvents}
                  >
                    {t(cfg, 'clearEvents', 'Clear')}
                  </GroButton>
                </div>
              </div>
            ) : null}
            {eventOpts.length > 0 ? (
              <div
                className="gro-events-grid"
                role="group"
                aria-label={t(cfg, 'eventsGridLabel', 'Automation events')}
              >
                {eventOpts.map((ev) => {
                  const selected = te.events.includes(ev.value);
                  const label = ev.label || ev.value;
                  const showSlug = Boolean(ev.label && ev.label !== ev.value);

                  return (
                    <label
                      key={ev.value}
                      className={`gro-event-chip${selected ? ' gro-event-chip--selected' : ''}`}
                    >
                      <input
                        type="checkbox"
                        className="gro-event-chip__input"
                        checked={selected}
                        onChange={(e) => toggleEvent(ev.value, e.target.checked)}
                      />
                      <span className="gro-event-chip__indicator" aria-hidden="true">
                        <span className="gro-event-chip__check" />
                      </span>
                      <span className="gro-event-chip__body">
                        <span className="gro-event-chip__label">{label}</span>
                        {showSlug ? <span className="gro-event-chip__slug">{ev.value}</span> : null}
                      </span>
                    </label>
                  );
                })}
              </div>
            ) : (
              <p className="gro-empty-state gro-events-picker__empty">
                {t(cfg, 'eventsCatalogEmpty', 'No automation events are registered yet. Add custom event names below.')}
              </p>
            )}
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
