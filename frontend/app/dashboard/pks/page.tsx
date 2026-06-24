'use client';

import { useState } from 'react';
import DashboardLayout from '@/src/components/DashboardLayout';
import ProtectedRoute from '@/src/components/ProtectedRoute';
import FormPengajuanPKS from '@/src/components/FormPengajuanPKS';

type TabStatus = 'draft' | 'review' | 'approval' | 'active';

export default function PKSPage() {
  const [activeTab, setActiveTab] = useState<TabStatus>('draft');
  const [showForm, setShowForm] = useState(false);

  if (showForm) {
    return (
      <ProtectedRoute>
        <DashboardLayout>
          <FormPengajuanPKS onCancel={() => setShowForm(false)} />
        </DashboardLayout>
      </ProtectedRoute>
    );
  }

  return (
    <ProtectedRoute>
      <DashboardLayout>
        <div className="space-y-6">
          {/* Header */}
          <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">Perjanjian Kerjasama</h1>
              <p className="text-gray-600 mt-2">Sistem pemantauan, negosiasi, dan manajemen kemitraan pihak ketiga RS Taman Harapan Baru</p>
            </div>
            <div className="flex flex-wrap items-center gap-3">
              {/* Kerjasama Baru */}
              <button
                onClick={() => setShowForm(true)}
                className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-medium transition-colors shadow-sm hover:shadow-md"
              >
                <span className="text-xl">+</span>
                Kerjasama Baru
              </button>
              {/* Import Excel */}
              <button className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium transition-colors shadow-sm hover:shadow-md">
                <span className="text-xl">📥</span>
                Import Excel
              </button>
              {/* Export Excel */}
              <button className="flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 rounded-xl font-medium transition-colors shadow-sm hover:shadow-md">
                <span className="text-xl">📄</span>
                Export Excel
              </button>
              {/* Download Template */}
              <div className="flex flex-col items-center gap-1">
                <button className="flex items-center gap-2 px-3 py-2 text-emerald-700 hover:text-emerald-800 font-medium transition-colors">
                  <span className="text-xl">⬇️</span>
                  Download Template
                </button>
              </div>
            </div>
          </div>

          {/* Status Cards */}
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            {/* Total Draft */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-2xl">
                  📝
                </div>
                <div>
                  <p className="text-sm text-gray-500">Total Draft</p>
                  <h3 className="text-2xl font-bold text-gray-900">1</h3>
                </div>
              </div>
            </div>
            {/* Menunggu Review */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center text-2xl">
                  ⏰
                </div>
                <div>
                  <p className="text-sm text-gray-500">Menunggu Review</p>
                  <h3 className="text-2xl font-bold text-gray-900">0</h3>
                </div>
              </div>
            </div>
            {/* Menunggu Persetujuan */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center text-2xl">
                  ✍️
                </div>
                <div>
                  <p className="text-sm text-gray-500">Menunggu Persetujuan</p>
                  <h3 className="text-2xl font-bold text-gray-900">0</h3>
                </div>
              </div>
            </div>
            {/* Dokumen Aktif */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center text-2xl">
                  ✅
                </div>
                <div>
                  <p className="text-sm text-gray-500">Dokumen Aktif</p>
                  <h3 className="text-2xl font-bold text-gray-900">0</h3>
                </div>
              </div>
            </div>
            {/* Dokumen Kadaluarsa */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-red-100 text-red-600 rounded-xl flex items-center justify-center text-2xl">
                  ⚠️
                </div>
                <div>
                  <p className="text-sm text-gray-500">Dokumen Kadaluarsa</p>
                  <h3 className="text-2xl font-bold text-gray-900">0</h3>
                </div>
              </div>
            </div>
            {/* Dokumen Dicabut */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-gray-100 text-gray-600 rounded-xl flex items-center justify-center text-2xl">
                  🚫
                </div>
                <div>
                  <p className="text-sm text-gray-500">Dokumen Dicabut</p>
                  <h3 className="text-2xl font-bold text-gray-900">0</h3>
                </div>
              </div>
            </div>
          </div>

          {/* Tabs */}
          <div className="flex flex-wrap gap-1 bg-gray-100 p-1 rounded-2xl">
            <button
              onClick={() => setActiveTab('draft')}
              className={`px-5 py-2.5 rounded-xl font-medium transition-all ${
                activeTab === 'draft'
                  ? 'bg-white text-gray-900 shadow-md'
                  : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200'
              }`}
            >
              Draft
            </button>
            <button
              onClick={() => setActiveTab('review')}
              className={`px-5 py-2.5 rounded-xl font-medium transition-all ${
                activeTab === 'review'
                  ? 'bg-white text-gray-900 shadow-md'
                  : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200'
              }`}
            >
              Review Legal
            </button>
            <button
              onClick={() => setActiveTab('approval')}
              className={`px-5 py-2.5 rounded-xl font-medium transition-all ${
                activeTab === 'approval'
                  ? 'bg-white text-gray-900 shadow-md'
                  : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200'
              }`}
            >
              Approval
            </button>
            <button
              onClick={() => setActiveTab('active')}
              className={`px-5 py-2.5 rounded-xl font-medium transition-all ${
                activeTab === 'active'
                  ? 'bg-white text-gray-900 shadow-md'
                  : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200'
              }`}
            >
              Publish/Aktif
            </button>
          </div>

          {/* Placeholder Content for Tabs */}
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
            <div className="text-center py-12">
              <div className="text-8xl mb-4 opacity-30">📄</div>
              <h3 className="text-xl font-semibold text-gray-700 mb-2">
                Data {activeTab.charAt(0).toUpperCase() + activeTab.slice(1)} Kosong
              </h3>
              <p className="text-gray-500 max-w-md mx-auto">
                {activeTab === 'draft' && 'Belum ada draft kontrak yang dibuat. Klik "Kerjasama Baru" untuk memulai.'}
                {activeTab === 'review' && 'Belum ada kontrak yang masuk ke tahap review legal.'}
                {activeTab === 'approval' && 'Belum ada kontrak yang menunggu persetujuan.'}
                {activeTab === 'active' && 'Belum ada kontrak yang aktif dan dipublikasikan.'}
              </p>
            </div>
          </div>
        </div>
      </DashboardLayout>
    </ProtectedRoute>
  );
}
