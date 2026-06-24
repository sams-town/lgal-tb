'use client';

import { useEffect, useState } from 'react';
import DashboardLayout from '@/src/components/DashboardLayout';
import ProtectedRoute from '@/src/components/ProtectedRoute';

interface DashboardStats {
  totalDokumenLegal: number;
  sipExpiring: number;
  strExpiring: number;
  progressAkreditasi: number;
  totalSuratMasuk: number;
  totalSuratKeluar: number;
  kpiDireksi: number;
  monitoringRisiko: number;
}

export default function DashboardPage() {
  const [user, setUser] = useState<any>(null);
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || '/new-hospital/dist/api';

  useEffect(() => {
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
      setUser(JSON.parse(storedUser));
    }
  }, []);

  useEffect(() => {
    if (user) {
      fetchStats();
    }
  }, [user]);

  const fetchStats = async () => {
    try {
      const res = await fetch(`${apiBaseUrl}/get_dashboard_stats.php`);
      const data = await res.json();
      if (res.ok && data.status === 'success') {
        setStats(data.data);
      } else {
        // Default mock data
        setStats({
          totalDokumenLegal: 1247,
          sipExpiring: 23,
          strExpiring: 45,
          progressAkreditasi: 87,
          totalSuratMasuk: 856,
          totalSuratKeluar: 623,
          kpiDireksi: 92,
          monitoringRisiko: 12
        });
      }
    } catch (error) {
      // Default mock data if API fails
      setStats({
        totalDokumenLegal: 1247,
        sipExpiring: 23,
        strExpiring: 45,
        progressAkreditasi: 87,
        totalSuratMasuk: 856,
        totalSuratKeluar: 623,
        kpiDireksi: 92,
        monitoringRisiko: 12
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <ProtectedRoute>
      <DashboardLayout>
        <div className="space-y-8">
          {/* Greeting */}
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Selamat Datang, Direktur</h1>
            <p className="text-gray-600 mt-2">Dashboard Overview Sistem Informasi Legal & Corporate Secretary Rumah Sakit</p>
          </div>

          {/* Stat Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {/* Total Dokumen Legal */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Total Dokumen Legal</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {loading ? '...' : stats?.totalDokumenLegal}
                  </h3>
                  <p className="text-sm text-emerald-600 mt-1 font-medium">+12%</p>
                </div>
                <div className="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">
                  📄
                </div>
              </div>
            </div>

            {/* SIP Akan Expired */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-gray-500 mb-1">SIP Akan Expired</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {loading ? '...' : stats?.sipExpiring}
                  </h3>
                  <p className="text-sm text-amber-600 mt-1 font-medium">H-30</p>
                </div>
                <div className="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-500 rounded-2xl flex items-center justify-center text-3xl">
                  ⚠️
                </div>
              </div>
            </div>

            {/* STR Akan Expired */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-gray-500 mb-1">STR Akan Expired</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {loading ? '...' : stats?.strExpiring}
                  </h3>
                  <p className="text-sm text-orange-600 mt-1 font-medium">H-60</p>
                </div>
                <div className="w-16 h-16 bg-gradient-to-br from-orange-500 to-red-500 rounded-2xl flex items-center justify-center text-3xl">
                  👨‍⚕️
                </div>
              </div>
            </div>

            {/* Progress Akreditasi */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Progress Akreditasi</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {loading ? '...' : `${stats?.progressAkreditasi}%`}
                  </h3>
                  <p className="text-sm text-emerald-600 mt-1 font-medium">+5%</p>
                </div>
                <div className="w-16 h-16 bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-2xl flex items-center justify-center text-3xl">
                  📈
                </div>
              </div>
            </div>
          </div>

          {/* Second Row */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {/* Total Surat Masuk */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Total Surat Masuk</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {loading ? '...' : stats?.totalSuratMasuk}
                  </h3>
                  <p className="text-sm text-emerald-600 mt-1 font-medium">+8%</p>
                </div>
                <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">
                  ✉️
                </div>
              </div>
            </div>

            {/* Total Surat Keluar */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Total Surat Keluar</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {loading ? '...' : stats?.totalSuratKeluar}
                  </h3>
                  <p className="text-sm text-emerald-600 mt-1 font-medium">+5%</p>
                </div>
                <div className="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">
                  📤
                </div>
              </div>
            </div>

            {/* KPI Direksi */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-gray-500 mb-1">KPI Direksi</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {loading ? '...' : `${stats?.kpiDireksi}%`}
                  </h3>
                  <p className="text-sm text-emerald-600 mt-1 font-medium">+3%</p>
                </div>
                <div className="w-16 h-16 bg-gradient-to-br from-violet-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl">
                  🎯
                </div>
              </div>
            </div>

            {/* Monitoring Risiko */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Monitoring Risiko</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {loading ? '...' : stats?.monitoringRisiko}
                  </h3>
                  <p className="text-sm text-red-600 mt-1 font-medium">-2</p>
                </div>
                <div className="w-16 h-16 bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl flex items-center justify-center text-3xl">
                  🛡️
                </div>
              </div>
            </div>
          </div>
        </div>
      </DashboardLayout>
    </ProtectedRoute>
  );
}