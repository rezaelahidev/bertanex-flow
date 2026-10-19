import React, { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RulesVisualApp } from './RulesVisualApp';

const mount = document.getElementById('gro-rules-visual-root');

if (mount) {
  createRoot(mount).render(
    <StrictMode>
      <RulesVisualApp textareaId="gro_rules_json" />
    </StrictMode>,
  );
}
