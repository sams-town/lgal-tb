'use client';

import DashboardLayout from '@/src/components/DashboardLayout';
import FormSurat from '@/src/components/FormSurat';
import ProtectedRoute from '@/src/components/ProtectedRoute';

export default function SuratPage() {
  return (
    <ProtectedRoute>
      <DashboardLayout>
        <FormSurat />
      </DashboardLayout>
    </ProtectedRoute>
  );
}
