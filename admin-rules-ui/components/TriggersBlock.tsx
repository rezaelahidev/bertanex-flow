import { slugLabel, type EditableTrigger } from '../ruleModel';
import { GroButton } from './GroButton';
import { t, useCfg } from './ConfigContext';
import React, { useMemo } from 'react';

export interface TriggersBlockProps {
  ruleIndex: number;
  editable: EditableTrigger;
  onChange: (next: EditableTrigger) => void;
}

export function TriggersBlock({ ruleIndex, editable: te, onChange }: TriggersBlockProps): React.ReactElement {
  const cfg = useCfg();
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

  const TextControl = window.wp.components.TextControl;
  const SelectControl = window.wp.components.SelectControl;

  const modes = [
    ['none', t(cfg, 'triggerNone', 'Evaluate without event filter'), t(cfg, 'triggerNoneHint', 'Runs whenever rules are checked')],
    ['events', t(cfg, 'triggerEvents', 'On automation events'), t(cfg, 'eventsSelectedHint', 'Rule runs when any selected event fires.')],
    ['schedule', t(cfg, 'triggerSchedule', 'On a schedule (cron)'), t(cfg, 'triggerScheduleHint', 'WordPress cron recurrence')],
  ] as const;

  return (
    <section className="gro-action-form__section">
      <h3 className="gro-action-form__heading">{t(cfg, 'triggerHeading', 'When to run')}</h3>
      <div className="gro-action-form__body">
      <div className="gro-action-delay__mode gro-trigger-modes" role="radiogroup" aria-label={t(cfg, 'triggerHeading', 'When to run')}>
        {modes.map(([value, title, hint]) => (
          <button
            key={value}
            type="button"
            role="radio"
            aria-checked={te.mode === value}
            className={`gro-action-delay__mode-btn${te.mode === value ? ' is-active' : ''}`}
            onClick={() => {
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
          >
            <span className="gro-action-delay__mode-title">{title}</span>
            <span className="gro-action-delay__mode-desc">{hint}</span>
          </button>
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
                    <button
                      key={ev.value}
                      type="button"
                      className={`gro-event-option${selected ? ' is-selected' : ''}`}
                      aria-pressed={selected}
                      onClick={() => toggleEvent(ev.value, !selected)}
                    >
                      <span className="gro-event-option__label">{label.replace(/_/g, '_\u200b')}</span>
                      {showSlug ? <span className="gro-event-option__slug">{ev.value.replace(/_/g, '_\u200b')}</span> : null}
                    </button>
                  );
                })}
              </div>
            ) : (
              <p className="gro-empty-state gro-events-picker__empty">
                {t(cfg, 'eventsCatalogEmpty', 'No automation events are registered yet. Add custom event names below.')}
              </p>
            )}
          </div>
          <TextControl
            key={`ce-${extras.join('|')}-${ruleIndex}`}
            label={t(cfg, 'customEventsLabel', 'Additional event names (comma-separated)')}
            defaultValue={extras.join(', ')}
            onBlur={(event: React.FocusEvent<HTMLInputElement>) => syncCustomExtras(event.target.value)}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
        </>
      ) : null}
      {te.mode === 'schedule' ? (
        <div className="gro-schedule-fields">
          <TextControl
            label={t(cfg, 'hookLabel', 'Cron hook')}
            value={te.schedule.hook}
            onChange={(hook: string) => onChange({ ...te, schedule: { ...te.schedule, hook } })}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <SelectControl
            label={t(cfg, 'recurrenceLabel', 'Recurrence')}
            value={te.schedule.recurrence}
            options={(cfg.recurrences ?? []).map((r) => ({ label: slugLabel(cfg.recurrenceLabels, r), value: r }))}
            onChange={(recurrence: string) => onChange({ ...te, schedule: { ...te.schedule, recurrence } })}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
        </div>
      ) : null}
      </div>
    </section>
  );
}
