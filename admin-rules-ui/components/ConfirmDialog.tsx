import React from 'react';

export interface ConfirmDialogProps {
  isOpen: boolean;
  message: string;
  confirmLabel: string;
  cancelLabel?: string;
  onConfirm: () => void;
  onCancel: () => void;
}

export function ConfirmDialog({
  isOpen,
  message,
  confirmLabel,
  cancelLabel,
  onConfirm,
  onCancel,
}: ConfirmDialogProps): React.ReactElement | null {
  if (!isOpen) return null;
  const Dialog = window.wp.components.__experimentalConfirmDialog;
  if (!Dialog) return null;

  return (
    <Dialog
      isOpen
      onConfirm={onConfirm}
      onCancel={onCancel}
      confirmButtonText={confirmLabel}
      cancelButtonText={cancelLabel}
    >
      {message}
    </Dialog>
  );
}
