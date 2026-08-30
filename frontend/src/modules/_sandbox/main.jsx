import React from 'react';
import { createRoot } from 'react-dom/client';
import '../../styles/tracs-tailwind.css';
import './sandbox-theme.css';
import { SandboxApp } from './SandboxApp';

const root = document.getElementById('tracs-foundation-sandbox-root');

if (root) {
  createRoot(root).render(
    <React.StrictMode>
      <SandboxApp />
    </React.StrictMode>,
  );
}
