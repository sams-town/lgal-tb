'use client';

import { useState } from 'react';

interface FormData {
  jenisSurat: 'masuk' | 'keluar';
  nomorSurat: string;
  tanggal: string;
  perihal: string;
  pihakTerkait: string;
  file: File | null;
  statusDisposisi: 'belum' | 'sudah';
}

export default function FormSurat() {
  const [formData, setFormData] = useState<FormData>({
    jenisSurat: 'masuk',
    nomorSurat: '',
    tanggal: new Date().toISOString().split('T')[0],
    perihal: '',
    pihakTerkait: '',
    file: null,
    statusDisposisi: 'belum',
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);

  const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || '/new-hospital/dist/api';

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!formData.nomorSurat.trim()) {
      newErrors.nomorSurat = 'Nomor surat wajib diisi';
    }
    if (!formData.tanggal) {
      newErrors.tanggal = 'Tanggal wajib diisi';
    }
    if (!formData.perihal.trim()) {
      newErrors.perihal = 'Perihal wajib diisi';
    }
    if (!formData.pihakTerkait.trim()) {
      newErrors.pihakTerkait = formData.jenisSurat === 'masuk' ? 'Asal surat wajib diisi' : 'Tujuan surat wajib diisi';
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
      formDataToSend.append('jenisSurat', formData.jenisSurat);
      formDataToSend.append('nomorSurat', formData.nomorSurat);
      formDataToSend.append('tanggal', formData.tanggal);
      formDataToSend.append('perihal', formData.perihal);
      formDataToSend.append('pihakTerkait', formData.pihakTerkait);
      formDataToSend.append('statusDisposisi', formData.statusDisposisi);
      
      if (formData.file) {
        formDataToSend.append('file', formData.file);
      }

      const res = await fetch(`${apiBaseUrl}/surat/create.php`, {
        method: 'POST',
        body: formDataToSend,
      });

      const data = await res.json();

      if (res.ok && data.status === 'success') {
        setSuccess('Surat berhasil disimpan!');
        setFormData({
          jenisSurat: 'masuk',
          nomorSurat: '',
          tanggal: new Date().toISOString().split('T')[0],
          perihal: '',
          pihakTerkait: '',
          file: null,
          statusDisposisi: 'belum',
        });
        setErrors({});
      } else {
        setErrors({ submit: data.message || 'Gagal menyimpan surat' });
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
        <h2 className="text-2xl font-bold text-gray-800">Formulir Surat</h2>
        <p className="text-gray-600">Isi data surat masuk/keluar dengan lengkap</p>
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
        {/* Jenis Surat */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Jenis Surat</label>
          <div className="flex gap-4">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                name="jenisSurat"
                value="masuk"
                checked={formData.jenisSurat === 'masuk'}
                onChange={handleChange}
                className="w-4 h-4 text-blue-600"
              />
              <span>Surat Masuk</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                name="jenisSurat"
                value="keluar"
                checked={formData.jenisSurat === 'keluar'}
                onChange={handleChange}
                className="w-4 h-4 text-blue-600"
              />
              <span>Surat Keluar</span>
            </label>
          </div>
        </div>

        {/* Nomor Surat */}
        <div>
          <label htmlFor="nomorSurat" className="block text-sm font-medium text-gray-700 mb-2">
            Nomor Surat
          </label>
          <input
            type="text"
            id="nomorSurat"
            name="nomorSurat"
            value={formData.nomorSurat}
            onChange={handleChange}
            className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
              errors.nomorSurat ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
            }`}
            placeholder="Masukkan nomor surat"
          />
          {errors.nomorSurat && <p className="mt-1 text-sm text-red-600">{errors.nomorSurat}</p>}
        </div>

        {/* Tanggal */}
        <div>
          <label htmlFor="tanggal" className="block text-sm font-medium text-gray-700 mb-2">
            Tanggal Surat
          </label>
          <input
            type="date"
            id="tanggal"
            name="tanggal"
            value={formData.tanggal}
            onChange={handleChange}
            className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
              errors.tanggal ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
            }`}
          />
          {errors.tanggal && <p className="mt-1 text-sm text-red-600">{errors.tanggal}</p>}
        </div>

        {/* Perihal */}
        <div>
          <label htmlFor="perihal" className="block text-sm font-medium text-gray-700 mb-2">
            Perihal
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
            placeholder="Masukkan perihal surat"
          />
          {errors.perihal && <p className="mt-1 text-sm text-red-600">{errors.perihal}</p>}
        </div>

        {/* Asal/Tujuan */}
        <div>
          <label htmlFor="pihakTerkait" className="block text-sm font-medium text-gray-700 mb-2">
            {formData.jenisSurat === 'masuk' ? 'Asal Surat' : 'Tujuan Surat'}
          </label>
          <input
            type="text"
            id="pihakTerkait"
            name="pihakTerkait"
            value={formData.pihakTerkait}
            onChange={handleChange}
            className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
              errors.pihakTerkait ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
            }`}
            placeholder={formData.jenisSurat === 'masuk' ? 'Masukkan asal surat' : 'Masukkan tujuan surat'}
          />
          {errors.pihakTerkait && <p className="mt-1 text-sm text-red-600">{errors.pihakTerkait}</p>}
        </div>

        {/* File Upload */}
        <div>
          <label htmlFor="file" className="block text-sm font-medium text-gray-700 mb-2">
            File Surat (PDF/DOC/DOCX)
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

        {/* Status Disposisi */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Status Disposisi</label>
          <select
            name="statusDisposisi"
            value={formData.statusDisposisi}
            onChange={handleChange}
            className="w-full px-4 py-3 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="belum">Belum Didisposisi</option>
            <option value="sudah">Sudah Didisposisi</option>
          </select>
        </div>

        {/* Submit Button */}
        <button
          type="submit"
          disabled={loading}
          className="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {loading ? 'Menyimpan...' : 'Simpan Surat'}
        </button>
      </form>
    </div>
  );
}
