import React from 'react';

export type GroIconName =
  | 'add'
  | 'delete'
  | 'edit'
  | 'duplicate'
  | 'up'
  | 'down'
  | 'save'
  | 'close'
  | 'condition'
  | 'action'
  | 'trigger'
  | 'clock'
  | 'settings';

const ICONS: Record<GroIconName, string> = {
  add: 'plus',
  delete: 'trash',
  edit: 'edit',
  duplicate: 'admin-page',
  up: 'arrow-up-alt2',
  down: 'arrow-down-alt2',
  save: 'saved',
  close: 'no-alt',
  condition: 'filter',
  action: 'controls-play',
  trigger: 'calendar-alt',
  clock: 'clock',
  settings: 'admin-generic',
};

export interface GroIconProps {
  name: GroIconName;
  className?: string;
}

export function GroIcon({ name }: GroIconProps): React.ReactElement {
  const Icon = window.wp.components.Icon;
  return <Icon icon={ICONS[name]} />;
}
