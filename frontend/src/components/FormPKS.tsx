'use client';

import { useState } from 'react';

interface FormData {
  namaMitra: string;
  perihal: string;
  tanggalMulai: string;
  tanggalAkhir: string;
  status: 'aktif' | 'berakhir' | 'perpanjangan';
  file: File | null;
}

export default function FormPKS() {
  const [formData, setFormData] = useState<FormData>({
    namaMitra: '',
    perihal: '',
    tanggalMulai: new Date().toISOString().split('T')[0],
    tanggalAkhir: '',
    status: 'aktif',
    file: null,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);

  const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || '/new-hospital/dist/api';

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!formData.namaMitra.trim()) {
      newErrors.namaMitra = 'Nama mitra wajib diisi';
    }
    if (!formData.perihal.trim()) {
      newErrors.perihal = 'Perihal wajib diisi';
    }
    if (!formData.tanggalMulai) {
      newErrors.tanggalMulai = 'Tanggal mulai wajib diisi';
    }
    if (!formData.tanggalAkhir) {
      newErrors.tanggalAkhir = 'Tanggal akhir wajib diisi';
    } else if (new Date(formData.tanggalAkhir) < new Date(formData.tanggalMulai)) {
      newErrors.tanggalAkhir = 'Tanggal akhir tidak boleh kurang dari tanggal mulai';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSuccess(null);
    setErrors({});

    if (!validate()) {
      return;
    }

    setLoading(true);

    try {
      const formDataToSend = new FormData();
      formDataToSend.append('namaMitra', formData.namaMitra);
      formDataToSend.append('perihal', formData.perihal);
      formDataToSend.append('tanggalMulai', formData.tanggalMulai);
      formDataToSend.append('tanggalAkhir', formData.tanggalAkhir);
      formDataToSend.append('status', formData.status);
      if (formData.file) {
        formDataToSend.append('file', formData.file);
      }

      const res = await fetch(`${apiBaseUrl}/save_pks.php`, {
        method: 'POST',
        body: formDataToSend,
      });

      const data = await res.json();

      if (res.ok && data.status === 'success') {
        setSuccess('Kontrak PKS berhasil disimpan!');
        setFormData({
          namaMitra: '',
          perihal: '',
          tanggalMulai: new Date().toISOString().split('T')[0],
          tanggalAkhir: '',
          status: 'aktif',
          file: null,
        });
        setErrors({});
      } else {
        setErrors({ submit: data.message || 'Gagal menyimpan kontrak PKS' });
      }
    } catch (err) {
      setErrors({ submit: 'Terjadi kesalahan, silakan coba lagi' });
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value, files } = e.target as any;
    setFormData((prev) => ({
      ...prev,
      [name]: files ? files[0] : value,
    }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  return (
    <div className="max-w-3xl mx-auto bg-white rounded-xl shadow-sm p-8">
      <div className="mb-6">
        <h2 className="text-2xl font-bold text-gray-800">Formulir Kontrak PKS</h2>
        <p className="text-gray-600">Isi data kontrak kerja sama dengan mitra RS</p>
      </div>

      {success && (
        <div className="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
          {success}
        </div>
      )}

      {errors.submit && (
        <div className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
          {errors.submit}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Nama Mitra */}
        <div>
          <label htmlFor="namaMitra" className="block text-sm font-medium text-gray-700 mb-2">
            Nama Mitra
          </label>
          <input
            type="text"
            id="namaMitra"
            name="namaMitra"
            value={formData.namaMitra}
            onChange={handleChange}
            className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
              errors.namaMitra ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
            }`}
            placeholder="Masukkan nama mitra"
          />
          {errors.namaMitra && <p className="mt-1 text-sm text-red-600">{errors.namaMitra}</p>}
        </div>

        {/* Perihal */}
        <div>
          <label htmlFor="perihal" className="block text-sm font-medium text-gray-700 mb-2">
            Perihal Kerja Sama
          </label>
          <textarea
            id="perihal"
            name="perihal"
            value={formData.perihal}
            onChange={handleChange}
            rows={3}
            className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
              errors.perihal ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
            }`}
            placeholder="Masukkan perihal kerja sama"
          />
          {errors.perihal && <p className="mt-1 text-sm text-red-600">{errors.perihal}</p>}
        </div>

        {/* Tanggal Range */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label htmlFor="tanggalMulai" className="block text-sm font-medium text-gray-700 mb-2">
              Tanggal Mulai
            </label>
            <input
              type="date"
              id="tanggalMulai"
              name="tanggalMulai"
              value={formData.tanggalMulai}
              onChange={handleChange}
              className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
                errors.tanggalMulai ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
              }`}
            />
            {errors.tanggalMulai && <p className="mt-1 text-sm text-red-600">{errors.tanggalMulai}</p>}
          </div>

          <div>
            <label htmlFor="tanggalAkhir" className="block text-sm font-medium text-gray-700 mb-2">
              Tanggal Akhir
            </label>
            <input
              type="date"
              id="tanggalAkhir"
              name="tanggalAkhir"
              value={formData.tanggalAkhir}
              onChange={handleChange}
              className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
                errors.tanggalAkhir ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
              }`}
            />
            {errors.tanggalAkhir && <p className="mt-1 text-sm text-red-600">{errors.tanggalAkhir}</p>}
          </div>
        </div>

        {/* Status */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Status Kontrak</label>
          <select
            name="status"
            value={formData.status}
            onChange={handleChange}
            className="w-full px-4 py-3 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="aktif">Aktif</option>
            <option value="berakhir">Berakhir</option>
            <option value="perpanjangan">Perpanjangan</option>
          </select>
        </div>

        {/* File Upload */}
        <div>
          <label htmlFor="file" className="block text-sm font-medium text-gray-700 mb-2">
            File Kontrak PKS (PDF/DOC/DOCX)
          </label>
          <input
            type="file"
            id="file"
            name="file"
            accept=".pdf,.doc,.docx"
            onChange={handleChange}
            className="w-full px-4 py-3 border border-dashed border-gray-300 rounded-lg"
          />
          {formData.file && (
            <p className="mt-2 text-sm text-gray-600">File terpilih: {formData.file.name}</p>
          )}
        </div>

        {/* Submit Button */}
        <button
          type="submit"
          disabled={loading}
          className="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {loading ? 'Menyimpan...' : 'Simpan Kontrak PKS'}
        </button>
      </form>
    </div>
  );
}
