'use client';

import DashboardLayout from '@/src/components/DashboardLayout';
import ProtectedRoute from '@/src/components/ProtectedRoute';

export default function RegulasiPage() {
  return (
    <ProtectedRoute>
      <DashboardLayout>
        <div className="space-y-6">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Regulasi</h1>
            <p className="text-gray-600 mt-2">Dokumen Regulasi & Kebijakan Rumah Sakit</p>
          </div>
          
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
            <div className="text-8xl mb-4 opacity-30">📜</div>
            <h3 className="text-xl font-semibold text-gray-700 mb-2">Halaman Regulasi</h3>
            <p className="text-gray-500 max-w-md mx-auto">Fitur manajemen dokumen regulasi akan ditambahkan di sini.</p>
          </div>
        </div>
      </DashboardLayout>
    </ProtectedRoute>
  );
}
