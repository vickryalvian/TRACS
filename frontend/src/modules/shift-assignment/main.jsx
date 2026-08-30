import React from 'react';
import { createRoot } from 'react-dom/client';
import '../../styles/tracs-tailwind.css';
import './styles.css';
import { ShiftAssignmentApp } from './ShiftAssignmentApp';

const root = document.getElementById('tracs-shift-assignment-root');

if (root) {
  createRoot(root).render(
    <React.StrictMode>
      <ShiftAssignmentApp />
    </React.StrictMode>,
  );
}
