'use client';

import DashboardLayout from '@/src/components/DashboardLayout';
import FormSOP from '@/src/components/FormSOP';
import ProtectedRoute from '@/src/components/ProtectedRoute';

export default function SOPPage() {
  return (
    <ProtectedRoute>
      <DashboardLayout>
        <FormSOP />
      </DashboardLayout>
    </ProtectedRoute>
  );
}
