'use client';

import { useState } from 'react';

interface Mitra {
  nama: string;
  pic: string;
}

interface FormData {
  tanggal: string;
  unitPengusul: string;
  jenisKerjasama: 'klinis' | 'non-klinis';
  objekKerjasama: string;
  analisa: string;
  mitra: Mitra[];
  keunggulan: string;
  kekurangan: string;
  biaya: string;
  referensi: string;
  capaianMutu: string;
}

export default function FormPengajuanPKS({ onCancel }: { onCancel: () => void }) {
  const [formData, setFormData] = useState<FormData>({
    tanggal: new Date().toISOString().split('T')[0],
    unitPengusul: '',
    jenisKerjasama: 'klinis',
    objekKerjasama: '',
    analisa: '',
    mitra: [
      { nama: '', pic: '' },
      { nama: '', pic: '' },
      { nama: '', pic: '' }
    ],
    keunggulan: '',
    kekurangan: '',
    biaya: '',
    referensi: '',
    capaianMutu: ''
  });

  const [loading, setLoading] = useState(false);

  const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || '/new-hospital/dist/api';

  const handleMitraChange = (index: number, field: keyof Mitra, value: string) => {
    const newMitra = [...formData.mitra];
    newMitra[index] = { ...newMitra[index], [field]: value };
    setFormData(prev => ({ ...prev, mitra: newMitra }));
  };

  const addMitra = () => {
    setFormData(prev => ({
      ...prev,
      mitra: [...prev.mitra, { nama: '', pic: '' }]
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const formDataToSend = new FormData();
      Object.entries(formData).forEach(([key, value]) => {
        if (key === 'mitra') {
          formDataToSend.append(key, JSON.stringify(value));
        } else {
          formDataToSend.append(key, value as string);
        }
      });

      const res = await fetch(`${apiBaseUrl}/save_pks.php`, {
        method: 'POST',
        body: formDataToSend
      });

      const data = await res.json();
      if (res.ok && data.status === 'success') {
        alert('Pengajuan Kerjasama berhasil disimpan!');
        onCancel();
      } else {
        alert(data.message || 'Gagal menyimpan pengajuan');
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Terjadi kesalahan saat menyimpan data');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-6xl mx-auto">
      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <h1 className="text-2xl font-bold text-gray-900 mb-8 border-b pb-4 border-gray-200">
          Formulir Pengajuan Perjanjian Kerjasama
        </h1>

        <form onSubmit={handleSubmit} className="space-y-8">
          {/* 1. INFORMASI DASAR */}
          <section>
            <h2 className="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
              1. INFORMASI DASAR
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Hari, Tanggal, Tahun</label>
                <input
                  type="date"
                  value={formData.tanggal}
                  onChange={(e) => setFormData(prev => ({ ...prev, tanggal: e.target.value }))}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Unit/Departemen Pengusul</label>
                <input
                  type="text"
                  value={formData.unitPengusul}
                  onChange={(e) => setFormData(prev => ({ ...prev, unitPengusul: e.target.value }))}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all"
                  placeholder="Masukkan unit pengusul"
                  required
                />
              </div>
            </div>
            <div className="mt-6">
              <label className="block text-sm font-medium text-gray-700 mb-3">Jenis Kerjasama</label>
              <div className="flex items-center gap-6">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    name="jenisKerjasama"
                    value="klinis"
                    checked={formData.jenisKerjasama === 'klinis'}
                    onChange={(e) => setFormData(prev => ({ ...prev, jenisKerjasama: 'klinis' }))}
                    className="w-4 h-4 text-emerald-600 border-gray-300 focus:ring-emerald-500"
                  />
                  <span className="text-gray-700">Klinis</span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    name="jenisKerjasama"
                    value="non-klinis"
                    checked={formData.jenisKerjasama === 'non-klinis'}
                    onChange={(e) => setFormData(prev => ({ ...prev, jenisKerjasama: 'non-klinis' }))}
                    className="w-4 h-4 text-emerald-600 border-gray-300 focus:ring-emerald-500"
                  />
                  <span className="text-gray-700">Non Klinis</span>
                </label>
              </div>
            </div>
            <div className="mt-6">
              <label className="block text-sm font-medium text-gray-700 mb-2">Objek Kerjasama</label>
              <input
                type="text"
                value={formData.objekKerjasama}
                onChange={(e) => setFormData(prev => ({ ...prev, objekKerjasama: e.target.value }))}
                className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all"
                placeholder="Masukkan objek kerjasama"
                required
              />
            </div>
          </section>

          {/* 2. ANALISIS & USULAN MITRA */}
          <section>
            <h2 className="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
              2. ANALISIS & USULAN MITRA
            </h2>
            <div className="mb-6">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Analisa Dasar/Alasan Pengajuan Kerjasama
              </label>
              <textarea
                value={formData.analisa}
                onChange={(e) => setFormData(prev => ({ ...prev, analisa: e.target.value }))}
                rows={5}
                className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all resize-vertical"
                placeholder="Jelaskan dasar, alasan, rencana, dan budget..."
                required
              />
            </div>
            <div>
              <div className="flex justify-between items-center mb-3">
                <label className="text-sm font-medium text-gray-700">Tabel Usulan Calon Mitra Kerjasama</label>
                <button
                  type="button"
                  onClick={addMitra}
                  className="text-sm text-emerald-700 hover:text-emerald-800 font-medium flex items-center gap-1"
                >
                  <span>+</span> Tambah Mitra
                </button>
              </div>
              <div className="overflow-x-auto border border-gray-200 rounded-xl">
                <table className="w-full">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-4 py-3 text-left text-sm font-medium text-gray-700 border-b">No</th>
                      <th className="px-4 py-3 text-left text-sm font-medium text-gray-700 border-b">Nama Calon Mitra Kerjasama</th>
                      <th className="px-4 py-3 text-left text-sm font-medium text-gray-700 border-b">Narahubung / PIC Mitra</th>
                    </tr>
                  </thead>
                  <tbody>
                    {formData.mitra.map((mitra, index) => (
                      <tr key={index} className="border-b border-gray-100">
                        <td className="px-4 py-3 text-gray-600">{index + 1}</td>
                        <td className="px-4 py-3">
                          <input
                            type="text"
                            value={mitra.nama}
                            onChange={(e) => handleMitraChange(index, 'nama', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all"
                            placeholder="Nama mitra"
                          />
                        </td>
                        <td className="px-4 py-3">
                          <input
                            type="text"
                            value={mitra.pic}
                            onChange={(e) => handleMitraChange(index, 'pic', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all"
                            placeholder="PIC mitra"
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          {/* 3. EVALUASI & RINCIAN */}
          <section>
            <h2 className="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
              3. EVALUASI & RINCIAN
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Keunggulan Calon Mitra Kerjasama
                </label>
                <textarea
                  value={formData.keunggulan}
                  onChange={(e) => setFormData(prev => ({ ...prev, keunggulan: e.target.value }))}
                  rows={4}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all resize-vertical"
                  placeholder="Jelaskan keunggulan mitra..."
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Kekurangan Calon Mitra Kerjasama
                </label>
                <textarea
                  value={formData.kekurangan}
                  onChange={(e) => setFormData(prev => ({ ...prev, kekurangan: e.target.value }))}
                  rows={4}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all resize-vertical"
                  placeholder="Jelaskan kekurangan mitra..."
                />
              </div>
            </div>
            <div className="mt-6">
              <label className="block text-sm font-medium text-gray-700 mb-2">Biaya - Biaya</label>
              <textarea
                value={formData.biaya}
                onChange={(e) => setFormData(prev => ({ ...prev, biaya: e.target.value }))}
                rows={4}
                className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all resize-vertical"
                placeholder="Rincian finansial..."
              />
            </div>
            <div className="mt-6">
              <label className="block text-sm font-medium text-gray-700 mb-2">Referensi Kerjasama</label>
              <textarea
                value={formData.referensi}
                onChange={(e) => setFormData(prev => ({ ...prev, referensi: e.target.value }))}
                rows={4}
                className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all resize-vertical"
                placeholder="Mitra yang sudah bekerjasama..."
              />
            </div>
            <div className="mt-6">
              <label className="block text-sm font-medium text-gray-700 mb-2">Capaian Mutu Kerjasama</label>
              <textarea
                value={formData.capaianMutu}
                onChange={(e) => setFormData(prev => ({ ...prev, capaianMutu: e.target.value }))}
                rows={4}
                className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all resize-vertical"
                placeholder="Capaian mutu yang diharapkan..."
              />
            </div>
          </section>

          {/* Action Buttons */}
          <div className="flex items-center gap-4 pt-6 border-t border-gray-200">
            <button
              type="button"
              onClick={onCancel}
              className="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-medium transition-colors"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={loading}
              className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-medium transition-colors shadow-sm hover:shadow-md"
            >
              {loading ? 'Menyimpan...' : 'Simpan Pengajuan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
