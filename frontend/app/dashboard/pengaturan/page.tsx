'use client';

import DashboardLayout from '@/src/components/DashboardLayout';
import ProtectedRoute from '@/src/components/ProtectedRoute';

export default function PengaturanPage() {
  return (
    <ProtectedRoute>
      <DashboardLayout>
        <div className="max-w-3xl mx-auto bg-white rounded-xl shadow-sm p-8">
          <h2 className="text-2xl font-bold text-gray-800 mb-6">Pengaturan</h2>
          <p className="text-gray-600">Halaman pengaturan akan segera tersedia.</p>
        </div>
      </DashboardLayout>
    </ProtectedRoute>
  );
}
