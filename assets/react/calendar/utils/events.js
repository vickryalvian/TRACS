export const EVENT_TYPES = [
  ['all', 'All'],
  ['case', 'Cases'],
  ['shift', 'Shifts'],
  ['meeting', 'Meetings'],
  ['reminder', 'Reminders'],
  ['task', 'Tasks'],
  ['holiday', 'Holidays'],
  ['maintenance', 'Maintenance'],
  ['overtime', 'Overtime'],
  ['birthday', 'Birthdays'],
];

export const STATUSES = [
  ['all', 'All statuses'],
  ['active', 'Active'],
  ['upcoming', 'Upcoming'],
  ['done', 'Done'],
  ['overdue', 'Overdue'],
  ['on_hold', 'On Hold'],
  ['cancelled', 'Cancelled'],
  ['holiday', 'Holiday'],
  ['maintenance', 'Maintenance'],
];

export const TYPE_LABELS = Object.fromEntries(EVENT_TYPES);

export const TYPE_TONES = {
  case: 'blue',
  shift: 'green',
  meeting: 'purple',
  reminder: 'amber',
  task: 'amber',
  holiday: 'red',
  maintenance: 'orange',
  overtime: 'red',
  birthday: 'purple',
};

export const WEEK_GROUPS = [
  ['shift', 'Shift Schedule'],
  ['case', 'Cases'],
  ['meeting', 'Meetings'],
  ['reminder', 'Reminders'],
  ['task', 'Tasks'],
  ['maintenance', 'Maintenance / Notifications'],
];

export function eventTypeLabel(type) {
  return TYPE_LABELS[type] || type.replaceAll('_', ' ');
}

export function sourceLabel(source) {
  return {
    cases: 'Cases',
    shifts: 'Shifting Assignment',
    meetings: 'Meetings / MoM',
    meeting_actions: 'MoM Actions',
    reminders: 'Reminders',
    tasks: 'Checklist',
    holidays: 'Public Holidays',
    notifications: 'Notifications',
    domains: 'Domains',
    users: 'User Management',
    calendar: 'Calendar',
  }[source] || source.replaceAll('_', ' ');
}
