import React from 'react';
import { createRoot } from 'react-dom/client';
import { CalendarApp } from './CalendarApp';
import './styles.css';

const root = document.getElementById('calendar-react-root');
if (root) {
  createRoot(root).render(
    <React.StrictMode>
      <CalendarApp />
    </React.StrictMode>,
  );
}
