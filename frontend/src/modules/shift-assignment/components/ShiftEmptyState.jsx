import { EmptyState } from '../../../components/ui/EmptyState';

export function ShiftEmptyState() {
  return (
    <EmptyState
      description="No scoped assignments match this view and filter combination. Existing schedules have not been changed."
      title="No shift assignments found"
    />
  );
}
