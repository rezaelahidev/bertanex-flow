import React from 'react';

export type GroButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost';
export type GroButtonSize = 'sm' | 'md' | 'lg';

const ICONS: Record<string, string> = {
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

export interface GroButtonProps extends Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, 'onClick'> {
  icon?: keyof typeof ICONS | string;
  variant?: GroButtonVariant;
  size?: GroButtonSize;
  iconOnly?: boolean;
  children?: React.ReactNode;
  onClick?: (event: React.MouseEvent<HTMLButtonElement>) => void;
}

export const GroButton = React.forwardRef<HTMLButtonElement, GroButtonProps>(function GroButton(
  { icon, variant = 'secondary', size = 'md', iconOnly = false, children, className, ...rest },
  ref,
): React.ReactElement {
  const Button = window.wp.components.Button;
  const wpVariant = variant === 'primary' ? 'primary' : variant === 'secondary' ? 'secondary' : 'tertiary';

  return (
    <Button
      ref={ref}
      variant={wpVariant}
      isDestructive={variant === 'danger'}
      size={size === 'sm' ? 'compact' : undefined}
      __next40pxDefaultSize={size !== 'sm'}
      icon={icon ? ICONS[icon] ?? icon : undefined}
      className={className}
      {...rest}
    >
      {iconOnly ? undefined : children}
    </Button>
  );
});
