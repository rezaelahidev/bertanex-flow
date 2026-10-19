import {
  computeDelaySeconds,
  defaultDelaySchedule,
  formatDelayPreview,
  todayIsoDate,
  type ActionDelaySchedule,
  type ActionDelayTimeMode,
} from '../delaySchedule';
import { DateInput } from './DateInput';
import { t, useCfg } from './ConfigContext';
import React, { useMemo } from 'react';

export interface ActionDelayFieldProps {
  schedule: ActionDelaySchedule;
  onChange: (next: ActionDelaySchedule, delaySeconds: number) => void;
}

/** Persist schedule; delay_seconds is a fallback — runtime resolves _delay from match time. */
function syncSchedule(schedule: ActionDelaySchedule, onChange: ActionDelayFieldProps['onChange']): void {
  onChange(schedule, computeDelaySeconds(schedule, new Date()));
}

export function ActionDelayField({ schedule, onChange }: ActionDelayFieldProps): React.ReactElement {
  const cfg = useCfg();
  const minDate = todayIsoDate();
  const preview = useMemo(() => formatDelayPreview(schedule, cfg), [schedule, cfg]);

  const setActive = (active: boolean): void => {
    if (!active) {
      syncSchedule({ ...defaultDelaySchedule(), active: false }, onChange);
      return;
    }
    syncSchedule({ ...schedule, active: true }, onChange);
  };

  const patch = (partial: Partial<ActionDelaySchedule>): void => {
    syncSchedule({ ...schedule, ...partial }, onChange);
  };

  return (
    <div className="gro-action-delay" role="group" aria-label={t(cfg, 'delayLabel', 'Delay')}>
      <div className="gro-action-delay__mode">
        <button
          type="button"
          className={`gro-action-delay__mode-btn${!schedule.active ? ' is-active' : ''}`}
          aria-pressed={!schedule.active}
          onClick={() => setActive(false)}
        >
          <span className="gro-action-delay__mode-title">{t(cfg, 'delayImmediateShort', 'Immediate')}</span>
          <span className="gro-action-delay__mode-desc">{t(cfg, 'delayModeImmediateHint', 'When the rule matches')}</span>
        </button>
        <button
          type="button"
          className={`gro-action-delay__mode-btn${schedule.active ? ' is-active' : ''}`}
          aria-pressed={schedule.active}
          onClick={() => setActive(true)}
        >
          <span className="gro-action-delay__mode-title">{t(cfg, 'delayModeSchedule', 'Schedule')}</span>
          <span className="gro-action-delay__mode-desc">{t(cfg, 'delayModeScheduleHint', 'Pick date and time after now')}</span>
        </button>
      </div>

      {schedule.active ? (
        <>
          <div className="gro-action-delay__panels">
            <section className="gro-action-delay__panel" aria-labelledby="gro-delay-date-heading">
              <header className="gro-action-delay__panel-head" id="gro-delay-date-heading">
                <span>{t(cfg, 'delayDateLegend', 'Date')}</span>
              </header>
              <div className="gro-action-delay__chips" role="group" aria-label={t(cfg, 'delayDateLegend', 'Date')}>
                <DelayChip
                  selected={schedule.dateMode === 'today'}
                  onClick={() => patch({ dateMode: 'today' })}
                >
                  {t(cfg, 'delayDateToday', 'Today')}
                </DelayChip>
                <DelayChip
                  selected={schedule.dateMode === 'days'}
                  onClick={() => patch({ dateMode: 'days', days: schedule.days > 0 ? schedule.days : 1 })}
                >
                  {t(cfg, 'delayDateInDays', 'In')} … {t(cfg, 'delayDateDaysLabel', 'days')}
                </DelayChip>
                <DelayChip
                  selected={schedule.dateMode === 'custom'}
                  onClick={() =>
                    patch({
                      dateMode: 'custom',
                      customDate: schedule.customDate || minDate,
                    })
                  }
                >
                  {t(cfg, 'delayDateOn', 'On')} …
                </DelayChip>
              </div>
              {schedule.dateMode === 'days' ? (
                React.createElement(window.wp.components.TextControl, {
                  label: `${t(cfg, 'delayDateInDays', 'In')} ${t(cfg, 'delayDateDaysLabel', 'days')}`,
                  type: 'number',
                  min: 1,
                  max: 365,
                  value: String(schedule.days),
                  onChange: (value: string) => patch({ dateMode: 'days', days: parseInt(value, 10) || 1 }),
                  __nextHasNoMarginBottom: true,
                  __next40pxDefaultSize: true,
                })
              ) : null}
              {schedule.dateMode === 'custom' ? (
                <div className="gro-action-delay__detail">
                  <label className="gro-action-delay__detail-label">
                    <span>{t(cfg, 'delayDateOn', 'On')}</span>
                    <DateInput
                      className="gro-action-delay__date"
                      min={minDate}
                      value={schedule.customDate || minDate}
                      onChange={(iso) => patch({ dateMode: 'custom', customDate: iso })}
                    />
                  </label>
                </div>
              ) : null}
              <div className="gro-action-delay__detail gro-action-delay__detail--clock">
                <label className="gro-action-delay__detail-label gro-action-delay__detail-label--stack">
                  <span>{t(cfg, 'delayClockOnDay', 'At time on that day')}</span>
                  <input
                    type="time"
                    className="gro-action-delay__time"
                    value={schedule.customTime || ''}
                    onChange={(ev) => {
                      const v = ev.target.value;
                      if (v) {
                        patch({ timeMode: 'custom', customTime: v });
                      } else {
                        patch({ timeMode: 'none', customTime: '' });
                      }
                    }}
                  />
                </label>
                <p className="gro-action-delay__hint description">
                  {t(cfg, 'delayClockHint', 'Combine with the date above — e.g. in 1 day at 11:00')}
                </p>
              </div>
            </section>

            <section className="gro-action-delay__panel" aria-labelledby="gro-delay-time-heading">
              <header className="gro-action-delay__panel-head" id="gro-delay-time-heading">
                <span>{t(cfg, 'delayTimeLegend', 'Time')}</span>
              </header>
              <p className="gro-action-delay__panel-note description">
                {t(cfg, 'delayExtraTimeHint', 'Optional extra wait after the date and clock time above.')}
              </p>
              <div className="gro-action-delay__chips" role="group" aria-label={t(cfg, 'delayTimeLegend', 'Time')}>
                <DelayChip
                  selected={schedule.timeMode === 'none'}
                  onClick={() => patch({ timeMode: 'none', relativeAmount: 0 })}
                >
                  {t(cfg, 'delayTimeNoneShort', 'No extra delay')}
                </DelayChip>
                <DelayChip
                  selected={schedule.timeMode === 'seconds'}
                  onClick={() => patch({ timeMode: 'seconds', relativeAmount: schedule.relativeAmount > 0 ? schedule.relativeAmount : 30 })}
                >
                  + {t(cfg, 'delayTimeSeconds', 'seconds')}
                </DelayChip>
                <DelayChip
                  selected={schedule.timeMode === 'minutes'}
                  onClick={() => patch({ timeMode: 'minutes', relativeAmount: schedule.relativeAmount > 0 ? schedule.relativeAmount : 5 })}
                >
                  + {t(cfg, 'delayTimeMinutes', 'minutes')}
                </DelayChip>
                <DelayChip
                  selected={schedule.timeMode === 'hours'}
                  onClick={() => patch({ timeMode: 'hours', relativeAmount: schedule.relativeAmount > 0 ? schedule.relativeAmount : 1 })}
                >
                  + {t(cfg, 'delayTimeHours', 'hours')}
                </DelayChip>
              </div>
              {schedule.timeMode !== 'none' && schedule.timeMode !== 'custom' ? (
                <div className="gro-action-delay__detail">
                  <RelativeTimeDetail
                    mode={schedule.timeMode}
                    amount={schedule.relativeAmount}
                    cfg={cfg}
                    onAmount={(amount) => patch({ timeMode: schedule.timeMode, relativeAmount: amount })}
                  />
                </div>
              ) : null}
            </section>
          </div>

          <div className="gro-action-delay__preview" role="status">
            <span className="gro-action-delay__preview-icon" aria-hidden />
            <span className="gro-action-delay__preview-body">
              <span className="gro-action-delay__preview-label">{t(cfg, 'delayPreviewLabel', 'Preview:')}</span>
              <span className="gro-action-delay__preview-text">{preview}</span>
            </span>
          </div>
        </>
      ) : null}
    </div>
  );
}

function DelayChip({
  selected,
  onClick,
  children,
}: {
  selected: boolean;
  onClick: () => void;
  children: React.ReactNode;
}): React.ReactElement {
  const Button = window.wp.components.Button;
  return (
    <Button
      variant="secondary"
      className={`gro-delay-chip${selected ? ' is-selected' : ''}`}
      aria-pressed={selected}
      onClick={onClick}
    >
      {children}
    </Button>
  );
}

function RelativeTimeDetail({
  mode,
  amount,
  cfg,
  onAmount,
}: {
  mode: 'seconds' | 'minutes' | 'hours';
  amount: number;
  cfg: ReturnType<typeof useCfg>;
  onAmount: (n: number) => void;
}): React.ReactElement {
  const unit =
    mode === 'hours'
      ? t(cfg, 'delayTimeHours', 'hours')
      : mode === 'minutes'
        ? t(cfg, 'delayTimeMinutes', 'minutes')
        : t(cfg, 'delayTimeSeconds', 'seconds');
  const max = mode === 'hours' ? 168 : mode === 'minutes' ? 10080 : 86400;

  return React.createElement(window.wp.components.TextControl, {
    label: `+ ${unit}`,
    type: 'number',
    min: 1,
    max,
    value: String(amount > 0 ? amount : 1),
    onChange: (value: string) => onAmount(parseInt(value, 10) || 1),
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
  });
}
