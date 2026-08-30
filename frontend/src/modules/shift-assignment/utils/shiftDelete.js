export const DELETE_CONFIRMATION = 'DELETE';

export function validateDeleteConfirmation(value) {
  return value === DELETE_CONFIRMATION
    ? ''
    : `Type ${DELETE_CONFIRMATION} exactly to enable deletion.`;
}

export function isTemplateProtected(assignment) {
  return assignment?.source === 'monthly_template';
}

export function deleteDependencyNote(assignment) {
  const notes = [];
  if (assignment?.is_holiday) {
    notes.push('holiday coverage');
  }
  if (assignment?.is_overtime) {
    notes.push('overtime');
  }
  return notes.length
    ? `This assignment includes ${notes.join(' and ')} state. Its before-delete snapshot is retained for audited restoration.`
    : 'Warnings and supported dependent records are retained in the before-delete audit snapshot.';
}
