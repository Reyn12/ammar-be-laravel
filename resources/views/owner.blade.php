<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Dashboard Owner · Ammar</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-stone-100 text-stone-800 antialiased" x-data="ownerApp()" x-init="init()" x-cloak>

{{-- ============ LOGIN ============ --}}
<template x-if="!token">
    <main class="min-h-screen grid place-items-center px-4">
        <form @submit.prevent="login" class="w-full max-w-sm bg-white rounded-2xl shadow-sm border border-stone-200 p-7 space-y-5">
            <div class="text-center space-y-1">
                <div class="mx-auto h-12 w-12 rounded-2xl bg-amber-500 text-white grid place-items-center text-xl font-bold">A</div>
                <h1 class="text-xl font-semibold pt-2">Dashboard Owner</h1>
                <p class="text-sm text-stone-500">Masuk pakai akun owner yang sama dengan aplikasi.</p>
            </div>
            <div class="space-y-3">
                <input x-model="form.username" placeholder="Username" autocomplete="username" required
                       class="w-full rounded-xl border border-stone-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-400">
                <input x-model="form.password" type="password" placeholder="Password" autocomplete="current-password" required
                       class="w-full rounded-xl border border-stone-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-400">
            </div>
            <p x-show="error" x-text="error" class="text-sm text-red-600"></p>
            <button :disabled="busy" class="w-full rounded-xl bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white font-medium py-2.5 transition">
                <span x-text="busy ? 'Masuk…' : 'Masuk'"></span>
            </button>
        </form>
    </main>
</template>

{{-- ============ APP ============ --}}
<template x-if="token">
    <div class="min-h-screen">
        <header class="bg-white border-b border-stone-200 sticky top-0 z-20">
            <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="h-8 w-8 rounded-lg bg-amber-500 text-white grid place-items-center font-bold">A</div>
                    <span class="font-semibold">Ammar <span class="text-stone-400 font-normal">· Owner</span></span>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-stone-500 hidden sm:inline" x-text="user?.name"></span>
                    <button @click="reload()" class="px-3 py-1.5 rounded-lg border border-stone-200 hover:bg-stone-50">Refresh</button>
                    <button @click="logout()" class="px-3 py-1.5 rounded-lg border border-stone-200 hover:bg-stone-50 text-red-600">Keluar</button>
                </div>
            </div>
            <nav class="max-w-6xl mx-auto px-4 flex gap-1 overflow-x-auto">
                <template x-for="t in tabs" :key="t.id">
                    <button @click="go(t.id)"
                            :class="tab === t.id ? 'border-amber-500 text-amber-700' : 'border-transparent text-stone-500 hover:text-stone-800'"
                            class="px-3.5 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition" x-text="t.label"></button>
                </template>
            </nav>
        </header>

        <main class="max-w-6xl mx-auto px-4 py-6">
            <p x-show="loading" class="text-sm text-stone-400 mb-3">Memuat…</p>

            {{-- RINGKASAN --}}
            <section x-show="tab === 'home'" class="space-y-6">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <template x-for="s in stats" :key="s.label">
                        <div class="bg-white rounded-2xl border border-stone-200 p-4">
                            <p class="text-xs uppercase tracking-wide text-stone-400" x-text="s.label"></p>
                            <p class="text-2xl font-semibold mt-1" x-text="s.value"></p>
                            <p class="text-xs text-stone-400 mt-0.5" x-text="s.hint"></p>
                        </div>
                    </template>
                </div>
                <div class="grid lg:grid-cols-2 gap-4">
                    <div class="bg-white rounded-2xl border border-stone-200 p-5">
                        <h3 class="font-semibold mb-3">Menu terlaris hari ini</h3>
                        <template x-for="p in topProducts" :key="p.name">
                            <div class="mb-3">
                                <div class="flex justify-between text-sm"><span x-text="p.name"></span><span class="text-stone-500" x-text="p.qty + ' porsi'"></span></div>
                                <div class="h-2 rounded-full bg-stone-100 mt-1"><div class="h-2 rounded-full bg-amber-400" :style="`width:${p.pct}%`"></div></div>
                            </div>
                        </template>
                        <p x-show="!topProducts.length" class="text-sm text-stone-400">Belum ada pesanan lunas hari ini.</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-stone-200 p-5">
                        <h3 class="font-semibold mb-3">Pesanan terbaru</h3>
                        <template x-for="o in orders.slice(0, 6)" :key="o.id">
                            <div class="flex items-center justify-between py-2 border-b border-stone-100 last:border-0 text-sm">
                                <div><p class="font-medium" x-text="o.code"></p><p class="text-xs text-stone-400" x-text="'Meja ' + (o.table_number ?? '-') + ' · ' + time(o.created_at)"></p></div>
                                <div class="text-right"><p x-text="rp(o.total_amount)"></p><span class="text-xs px-2 py-0.5 rounded-full" :class="badge(o).cls" x-text="badge(o).text"></span></div>
                            </div>
                        </template>
                        <p x-show="!orders.length" class="text-sm text-stone-400">Belum ada pesanan.</p>
                    </div>
                </div>
            </section>

            {{-- MEJA --}}
            <section x-show="tab === 'tables'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Meja <span class="text-stone-400 font-normal text-sm" x-text="'(' + tables.length + ')'"></span></h2>
                    <button @click="open('table', {table_number: nextTableNumber()})" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium">+ Tambah meja</button>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <template x-for="t in tables" :key="t.id">
                        <div class="bg-white rounded-2xl border p-4 space-y-3" :class="t.is_active ? 'border-stone-200' : 'border-stone-200 opacity-60'">
                            <div class="flex items-center justify-between">
                                <p class="text-3xl font-bold" x-text="t.table_number"></p>
                                <button @click="toggleTable(t)" :title="t.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                        class="relative h-6 w-11 rounded-full transition" :class="t.is_active ? 'bg-emerald-500' : 'bg-stone-300'">
                                    <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all" :class="t.is_active ? 'left-5' : 'left-0.5'"></span>
                                </button>
                            </div>
                            <p class="text-xs" :class="t.is_active ? 'text-emerald-600' : 'text-stone-400'" x-text="t.is_active ? 'Aktif' : 'Nonaktif'"></p>
                            <div class="flex flex-wrap gap-1.5 text-xs">
                                <button @click="showQr(t)" class="px-2.5 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200">QR</button>
                                <button @click="open('table', {id: t.id, table_number: t.table_number})" class="px-2.5 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200">Ubah</button>
                                <button @click="regenQr(t)" class="px-2.5 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200">QR baru</button>
                                <button @click="removeTable(t)" class="px-2.5 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100">Hapus</button>
                            </div>
                        </div>
                    </template>
                </div>
                <p x-show="!tables.length && !loading" class="text-sm text-stone-400">Belum ada meja.</p>
            </section>

            {{-- STAF --}}
            <section x-show="tab === 'staff'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Akun staf</h2>
                    <button @click="open('staff', {role: 'cashier', is_active: true})" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium">+ Tambah akun</button>
                </div>
                <div class="bg-white rounded-2xl border border-stone-200 divide-y divide-stone-100">
                    <template x-for="u in staff" :key="u.id">
                        <div class="flex items-center justify-between gap-3 p-4">
                            <div class="min-w-0">
                                <p class="font-medium truncate" x-text="u.name"></p>
                                <p class="text-xs text-stone-400"><span x-text="'@' + u.username"></span> · <span x-text="u.role === 'cashier' ? 'Kasir' : 'Koki'"></span></p>
                            </div>
                            <div class="flex items-center gap-2 text-xs shrink-0">
                                <span class="px-2 py-0.5 rounded-full" :class="u.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500'" x-text="u.is_active ? 'Aktif' : 'Nonaktif'"></span>
                                <button @click="open('staff', {id: u.id, name: u.name, username: u.username, role: u.role, is_active: u.is_active, password: ''})" class="px-2.5 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200">Ubah</button>
                                <button @click="removeStaff(u)" class="px-2.5 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100">Hapus</button>
                            </div>
                        </div>
                    </template>
                    <p x-show="!staff.length && !loading" class="p-4 text-sm text-stone-400">Belum ada akun kasir/koki.</p>
                </div>
            </section>

            {{-- MENU --}}
            <section x-show="tab === 'menu'" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">Menu <span class="text-stone-400 font-normal text-sm" x-text="'(' + products.length + ')'"></span></h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <input x-model="search" placeholder="Cari menu…" class="rounded-xl border border-stone-300 px-3 py-2 text-sm w-40 focus:outline-none focus:ring-2 focus:ring-amber-400">
                        <button @click="catModal = true; catError = ''" class="px-3.5 py-2 rounded-xl border border-stone-300 bg-white hover:bg-stone-50 text-sm">Kelola kategori</button>
                        <button @click="openProduct()" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium">+ Tambah menu</button>
                    </div>
                </div>
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <button @click="catFilter = 'all'" class="px-3.5 py-1.5 rounded-full text-sm border whitespace-nowrap transition" :class="catFilter === 'all' ? 'bg-amber-500 border-amber-500 text-white' : 'bg-white border-stone-200 hover:bg-stone-50'">Semua</button>
                    <template x-for="c in categories" :key="c.id">
                        <button @click="catFilter = c.id" class="px-3.5 py-1.5 rounded-full text-sm border whitespace-nowrap transition" :class="catFilter === c.id ? 'bg-amber-500 border-amber-500 text-white' : 'bg-white border-stone-200 hover:bg-stone-50'" x-text="c.name + ' (' + c.product_count + ')'"></button>
                    </template>
                </div>
                <div class="bg-white rounded-2xl border border-stone-200 divide-y divide-stone-100">
                    <template x-for="p in filteredProducts" :key="p.id">
                        <div class="flex items-center gap-3 p-3">
                            <img :src="p.image_url" x-show="p.image_url" class="h-12 w-12 rounded-xl object-cover bg-stone-100" alt="" onerror="this.style.display='none'">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate" x-text="p.name"></p>
                                <p class="text-xs text-stone-400"><span x-text="p.category_name"></span> · <span x-text="rp(p.price)"></span><span x-show="(p.addon_groups || []).length" x-text="' · ' + p.addon_groups.length + ' grup pilihan'"></span></p>
                            </div>
                            <button @click="toggleProduct(p)" class="relative h-6 w-11 rounded-full transition shrink-0" :class="p.is_available ? 'bg-emerald-500' : 'bg-stone-300'" :title="p.is_available ? 'Tersedia' : 'Habis'">
                                <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all" :class="p.is_available ? 'left-5' : 'left-0.5'"></span>
                            </button>
                            <button @click="openProduct(p)" class="text-xs px-2.5 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200">Ubah</button>
                            <button @click="removeProduct(p)" class="text-xs px-2.5 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100">Hapus</button>
                        </div>
                    </template>
                    <p x-show="!filteredProducts.length && !loading" class="p-4 text-sm text-stone-400">Menu tidak ditemukan.</p>
                </div>
            </section>

            {{-- PESANAN --}}
            <section x-show="tab === 'orders'" class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <template x-for="f in orderFilters" :key="f.id">
                        <button @click="orderFilter = f.id" class="px-3.5 py-1.5 rounded-full text-sm border transition"
                                :class="orderFilter === f.id ? 'bg-amber-500 border-amber-500 text-white' : 'bg-white border-stone-200 hover:bg-stone-50'" x-text="f.label"></button>
                    </template>
                </div>
                <div class="space-y-3">
                    <template x-for="o in filteredOrders" :key="o.id">
                        <div class="bg-white rounded-2xl border border-stone-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold" x-text="o.code"></p>
                                    <p class="text-xs text-stone-400" x-text="'Meja ' + (o.table_number ?? '-') + ' · ' + (o.customer_name || 'Tanpa nama') + ' · ' + time(o.created_at) + ' · ' + (o.payment_method === 'cash' ? 'Tunai' : 'QRIS')"></p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="font-semibold" x-text="rp(o.total_amount)"></p>
                                    <span class="text-xs px-2 py-0.5 rounded-full" :class="badge(o).cls" x-text="badge(o).text"></span>
                                </div>
                            </div>
                            <ul class="mt-3 text-sm text-stone-600 space-y-0.5">
                                <template x-for="i in o.items" :key="i.id">
                                    <li><span x-text="i.qty + '× ' + i.product_name"></span><span class="text-stone-400" x-show="i.addons.length" x-text="' (' + i.addons.map(a => a.name).join(', ') + ')'"></span><span class="text-stone-400 italic" x-show="i.notes" x-text="' — ' + i.notes"></span></li>
                                </template>
                            </ul>
                            <button x-show="isPendingCash(o)" @click="confirmCash(o)" class="mt-3 px-3.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-sm">Konfirmasi tunai</button>
                        </div>
                    </template>
                    <p x-show="!filteredOrders.length && !loading" class="text-sm text-stone-400">Tidak ada pesanan.</p>
                </div>
            </section>

            {{-- SHIFT --}}
            <section x-show="tab === 'shifts'" class="space-y-4">
                <h2 class="text-lg font-semibold">Shift kasir</h2>
                <div class="space-y-3">
                    <template x-for="s in shifts" :key="s.id">
                        <div class="bg-white rounded-2xl border border-stone-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold" x-text="s.user_name"></p>
                                    <p class="text-xs text-stone-400" x-text="time(s.start_time, true) + (s.end_time ? ' → ' + time(s.end_time) : ' → berjalan')"></p>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded-full" :class="s.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500'" x-text="s.status === 'active' ? 'Aktif' : 'Selesai'"></span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3 text-sm">
                                <div><p class="text-xs text-stone-400">Modal awal</p><p x-text="rp(s.starting_cash)"></p></div>
                                <div><p class="text-xs text-stone-400">Perkiraan kas</p><p x-text="rp(s.expected_cash)"></p></div>
                                <div><p class="text-xs text-stone-400">Kas aktual</p><p x-text="s.actual_cash == null ? '-' : rp(s.actual_cash)"></p></div>
                                <div><p class="text-xs text-stone-400">Selisih</p><p :class="s.difference < 0 ? 'text-red-600' : ''" x-text="s.difference == null ? '-' : rp(s.difference)"></p></div>
                            </div>
                            <p class="text-xs text-stone-400 mt-2" x-text="s.cash_order_count + ' pesanan tunai · ' + s.qris_order_count + ' pesanan QRIS'"></p>
                            <button x-show="s.status === 'active'" @click="forceClose(s)" class="mt-3 px-3.5 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 text-sm">Tutup paksa</button>
                        </div>
                    </template>
                    <p x-show="!shifts.length && !loading" class="text-sm text-stone-400">Belum ada shift.</p>
                </div>
            </section>
        </main>

        {{-- MODAL FORM --}}
        <div x-show="modal" x-transition.opacity class="fixed inset-0 z-30 bg-stone-900/40 grid place-items-center p-4" @keydown.escape.window="modal = null" @click.self="modal = null">
            <form @submit.prevent="submitModal" class="bg-white rounded-2xl w-full max-w-sm p-6 space-y-4 shadow-xl">
                <h3 class="font-semibold text-lg" x-text="modalTitle"></h3>
                <template x-if="modal?.type === 'table'">
                    <div><label class="text-sm text-stone-500">Nomor meja</label>
                        <input x-model.number="modal.data.table_number" type="number" min="1" max="999" required class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-400"></div>
                </template>
                <template x-if="modal?.type === 'staff'">
                    <div class="space-y-3">
                        <input x-model="modal.data.name" placeholder="Nama" required class="w-full rounded-xl border border-stone-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-400">
                        <input x-model="modal.data.username" placeholder="Username" required class="w-full rounded-xl border border-stone-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-400">
                        <input x-model="modal.data.password" type="password" :placeholder="modal.data.id ? 'Password baru (kosongkan jika tidak diganti)' : 'Password'" :required="!modal.data.id" class="w-full rounded-xl border border-stone-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-400">
                        <select x-model="modal.data.role" :disabled="!!modal.data.id" class="w-full rounded-xl border border-stone-300 px-4 py-2.5 bg-white disabled:bg-stone-50">
                            <option value="cashier">Kasir</option><option value="kitchen">Koki</option>
                        </select>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="modal.data.is_active" class="rounded"> Akun aktif</label>
                    </div>
                </template>
                <p x-show="modalError" x-text="modalError" class="text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="modal = null" class="px-4 py-2 rounded-xl border border-stone-200 hover:bg-stone-50 text-sm">Batal</button>
                    <button :disabled="busy" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white text-sm font-medium">Simpan</button>
                </div>
            </form>
        </div>

        {{-- MODAL QR --}}
        <div x-show="qr" x-transition.opacity class="fixed inset-0 z-30 bg-stone-900/40 grid place-items-center p-4" @keydown.escape.window="qr = null" @click.self="qr = null">
            <div class="bg-white rounded-2xl w-full max-w-xs p-6 text-center space-y-3 shadow-xl">
                <h3 class="font-semibold text-lg" x-text="'QR Meja ' + qr?.table_number"></h3>
                <div id="qr-box" class="mx-auto w-[220px] h-[220px]"></div>
                <p class="text-xs text-stone-400 break-all" x-text="qr?.qr_url"></p>
                <div class="flex gap-2 justify-center text-sm">
                    <button @click="downloadQr()" class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white">Unduh PNG</button>
                    <button @click="navigator.clipboard.writeText(qr.qr_url).then(() => toast('Link disalin'))" class="px-3.5 py-2 rounded-xl border border-stone-200 hover:bg-stone-50">Salin link</button>
                    <button @click="qr = null" class="px-3.5 py-2 rounded-xl border border-stone-200 hover:bg-stone-50">Tutup</button>
                </div>
            </div>
        </div>

        {{-- MODAL KATEGORI --}}
        <div x-show="catModal" x-transition.opacity class="fixed inset-0 z-30 bg-stone-900/40 grid place-items-center p-4" @keydown.escape.window="catModal = false" @click.self="catModal = false">
            <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4 shadow-xl max-h-[90vh] overflow-y-auto">
                <h3 class="font-semibold text-lg">Kelola kategori</h3>
                <div class="space-y-2">
                    <template x-for="c in categories" :key="c.id">
                        <div class="flex items-center gap-2">
                            <input :value="c.name" @change="renameCategory(c, $event.target.value)" class="flex-1 min-w-0 rounded-xl border border-stone-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                            <span class="text-xs text-stone-400 w-16 text-right" x-text="c.product_count + ' menu'"></span>
                            <button @click="removeCategory(c)" class="text-xs px-2.5 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100">Hapus</button>
                        </div>
                    </template>
                    <p x-show="!categories.length" class="text-sm text-stone-400">Belum ada kategori.</p>
                </div>
                <form @submit.prevent="addCategory" class="flex gap-2 pt-1">
                    <input x-model="newCategory" placeholder="Nama kategori baru" class="flex-1 min-w-0 rounded-xl border border-stone-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    <button class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium">Tambah</button>
                </form>
                <p x-show="catError" x-text="catError" class="text-sm text-red-600"></p>
                <p class="text-xs text-stone-400">Ubah nama langsung di kolomnya (tersimpan saat kamu pindah dari kolom). Kategori yang masih punya menu tidak bisa dihapus.</p>
                <div class="flex justify-end"><button @click="catModal = false" class="px-4 py-2 rounded-xl border border-stone-200 hover:bg-stone-50 text-sm">Tutup</button></div>
            </div>
        </div>

        {{-- MODAL MENU --}}
        <div x-show="pm" x-transition.opacity class="fixed inset-0 z-30 bg-stone-900/40 grid place-items-center p-4" @keydown.escape.window="pm = null" @click.self="pm = null">
            <template x-if="pm">
            <form @submit.prevent="saveProduct" class="bg-white rounded-2xl w-full max-w-xl p-6 space-y-4 shadow-xl max-h-[92vh] overflow-y-auto">
                <h3 class="font-semibold text-lg" x-text="pm?.id ? 'Ubah menu' : 'Tambah menu'"></h3>

                <div class="flex items-center gap-3">
                    <div class="h-20 w-20 rounded-2xl bg-stone-100 overflow-hidden grid place-items-center text-xs text-stone-400 shrink-0">
                        <img x-show="pm?.preview" :src="pm?.preview" class="h-full w-full object-cover" alt="">
                        <span x-show="!pm?.preview">Foto</span>
                    </div>
                    <div class="text-sm">
                        <input type="file" accept="image/jpeg,image/png,image/webp" @change="pickPhoto($event)" class="text-xs">
                        <p class="text-xs text-stone-400 mt-1">JPG/PNG/WebP, maks 2 MB. Disarankan 1200×1032.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2"><label class="text-xs text-stone-500">Nama menu</label>
                        <input x-model="pm.name" required minlength="2" class="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"></div>
                    <div><label class="text-xs text-stone-500">Harga (Rp)</label>
                        <input x-model.number="pm.price" type="number" min="0" required class="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"></div>
                    <div><label class="text-xs text-stone-500">Kategori</label>
                        <select x-model.number="pm.category_id" required class="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2 text-sm bg-white">
                            <option value="" disabled>Pilih…</option>
                            <template x-for="c in categories" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                        </select></div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="pm.is_available" class="rounded"> Tersedia (matikan kalau sedang habis)</label>

                <div class="border-t border-stone-100 pt-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div><h4 class="font-semibold text-sm">Pilihan menu</h4>
                            <p class="text-xs text-stone-400">Mis. Level Es, Level Gula, Tambahan. Kosongkan kalau menunya tanpa pilihan.</p></div>
                    </div>

                    <template x-for="(g, gi) in pm.groups" :key="gi">
                        <div class="rounded-xl border border-stone-200 p-3 space-y-2 bg-stone-50/50">
                            <div class="flex gap-2">
                                <input x-model="g.name" placeholder="Nama grup (mis. Level Es)" class="flex-1 min-w-0 rounded-lg border border-stone-300 px-3 py-1.5 text-sm bg-white">
                                <select x-model="g.kind" class="rounded-lg border border-stone-300 px-2 py-1.5 text-sm bg-white">
                                    <option value="variant">Wajib pilih 1</option>
                                    <option value="addon">Opsional (boleh banyak)</option>
                                </select>
                                <button type="button" @click="pm.groups.splice(gi, 1)" class="text-xs px-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100" title="Hapus grup">✕</button>
                            </div>
                            <template x-for="(a, ai) in g.addons" :key="ai">
                                <div class="flex items-center gap-2 pl-2">
                                    <input x-model="a.name" placeholder="Nama pilihan" class="flex-1 min-w-0 rounded-lg border border-stone-300 px-3 py-1.5 text-sm bg-white">
                                    <input x-model.number="a.price" type="number" min="0" placeholder="+Rp" class="w-24 rounded-lg border border-stone-300 px-2 py-1.5 text-sm bg-white">
                                    <label class="flex items-center gap-1 text-xs text-stone-500" title="Tersedia"><input type="checkbox" x-model="a.is_available" class="rounded"> ada</label>
                                    <button type="button" @click="g.addons.splice(ai, 1)" class="text-xs px-2 py-1 rounded-lg text-red-600 hover:bg-red-50" title="Hapus pilihan">✕</button>
                                </div>
                            </template>
                            <button type="button" @click="g.addons.push({name: '', price: 0, is_available: true})" class="text-xs text-amber-700 hover:underline pl-2">+ Tambah pilihan</button>
                        </div>
                    </template>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="pm.groups.push({name: '', kind: 'variant', addons: [{name: '', price: 0, is_available: true}]})" class="text-sm px-3 py-1.5 rounded-lg border border-stone-300 bg-white hover:bg-stone-50">+ Tambah grup pilihan</button>
                        <select @change="copyOptions($event.target.value); $event.target.value = ''" class="text-sm rounded-lg border border-stone-300 px-2 py-1.5 bg-white">
                            <option value="">Salin pilihan dari menu lain…</option>
                            <template x-for="o in products.filter(x => x.id !== pm.id && (x.addon_groups || []).length)" :key="o.id"><option :value="o.id" x-text="o.name"></option></template>
                        </select>
                    </div>
                </div>

                <p x-show="pmError" x-text="pmError" class="text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="pm = null" class="px-4 py-2 rounded-xl border border-stone-200 hover:bg-stone-50 text-sm">Batal</button>
                    <button :disabled="busy" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white text-sm font-medium" x-text="busy ? 'Menyimpan…' : 'Simpan'"></button>
                </div>
            </form>
            </template>
        </div>

        {{-- TOAST --}}
        <div x-show="toastMsg" x-transition class="fixed bottom-5 left-1/2 -translate-x-1/2 z-40 px-4 py-2.5 rounded-xl text-sm text-white shadow-lg" :class="toastErr ? 'bg-red-600' : 'bg-stone-800'" x-text="toastMsg"></div>
    </div>
</template>

<script>
function ownerApp() {
    const KEY = 'ammar_owner_token';
    return {
        token: null, user: null, tab: 'home', loading: false, busy: false, error: '',
        form: {username: '', password: ''},
        tabs: [
            {id: 'home', label: 'Ringkasan'}, {id: 'tables', label: 'Meja'}, {id: 'staff', label: 'Staf'},
            {id: 'menu', label: 'Menu'}, {id: 'orders', label: 'Pesanan'}, {id: 'shifts', label: 'Shift'},
        ],
        orderFilters: [
            {id: 'all', label: 'Semua'}, {id: 'pending_cash', label: 'Menunggu tunai'},
            {id: 'paid', label: 'Berjalan'}, {id: 'completed', label: 'Selesai'},
        ],
        orders: [], tables: [], staff: [], products: [], shifts: [], categories: [],
        catFilter: 'all', catModal: false, newCategory: '', catError: '', pm: null, pmError: '',
        search: '', orderFilter: 'all',
        modal: null, modalError: '', qr: null,
        toastMsg: '', toastErr: false, _t: null,

        init() {
            try { this.token = sessionStorage.getItem(KEY); } catch (e) {}
            if (this.token) this.start();
        },

        // ---------- http ----------
        async api(path, method = 'GET', body = null) {
            const res = await fetch('/api/v1/' + path, {
                method,
                headers: {Accept: 'application/json', 'Content-Type': 'application/json', Authorization: 'Bearer ' + this.token},
                body: body ? JSON.stringify(body) : null,
            });
            const json = await res.json().catch(() => ({}));
            if (res.status === 401) { this.clearSession(); throw new Error('Sesi berakhir, silakan masuk lagi.'); }
            if (!res.ok || json.success === false) {
                const first = json.errors ? Object.values(json.errors)[0]?.[0] : null;
                throw new Error(first || json.message || 'Terjadi kesalahan.');
            }
            return json;
        },

        // ---------- auth ----------
        async login() {
            this.busy = true; this.error = '';
            try {
                const res = await fetch('/api/v1/auth/login', {
                    method: 'POST', headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
                    body: JSON.stringify(this.form),
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.errors?.username?.[0] || json.message || 'Login gagal.');
                if (json.data.user.role !== 'owner') {
                    await fetch('/api/v1/auth/logout', {method: 'POST', headers: {Accept: 'application/json', Authorization: 'Bearer ' + json.data.access_token}});
                    throw new Error('Dashboard ini khusus akun owner.');
                }
                this.token = json.data.access_token;
                try { sessionStorage.setItem(KEY, this.token); } catch (e) {}
                this.form.password = '';
                this.start();
            } catch (e) { this.error = e.message; }
            this.busy = false;
        },
        async logout() {
            try { await this.api('auth/logout', 'POST'); } catch (e) {}
            this.clearSession();
        },
        clearSession() {
            this.token = null; this.user = null;
            try { sessionStorage.removeItem(KEY); } catch (e) {}
        },
        async start() {
            try { this.user = (await this.api('auth/me')).data; } catch (e) { return; }
            if (this.user.role !== 'owner') { this.clearSession(); return; }
            this.reload();
        },

        // ---------- data ----------
        async reload() {
            this.loading = true;
            try {
                const [o, t, s, p, sh, c] = await Promise.all([
                    this.api('orders?limit=500'), this.api('tables'), this.api('users'),
                    this.api('products'), this.api('shifts'), this.api('categories'),
                ]);
                this.orders = o.data; this.tables = t.data; this.staff = s.data; this.products = p.data; this.shifts = sh.data; this.categories = c.data;
            } catch (e) { this.toast(e.message, true); }
            this.loading = false;
        },
        go(id) { this.tab = id; },

        // ---------- ringkasan ----------
        isToday(iso) { return new Date(iso).toDateString() === new Date().toDateString(); },
        get todayPaid() { return this.orders.filter(o => o.payment_status === 'paid' && this.isToday(o.paid_at || o.created_at)); },
        get stats() {
            const paid = this.todayPaid;
            const revenue = paid.reduce((n, o) => n + o.total_amount, 0);
            return [
                {label: 'Omzet hari ini', value: this.rp(revenue), hint: paid.length + ' pesanan lunas'},
                {label: 'Rata-rata/pesanan', value: this.rp(paid.length ? Math.round(revenue / paid.length) : 0), hint: 'hari ini'},
                {label: 'Perlu tindakan', value: this.orders.filter(o => this.isPendingCash(o)).length, hint: 'tunai belum dikonfirmasi'},
                {label: 'Meja aktif', value: this.tables.filter(t => t.is_active).length + '/' + this.tables.length, hint: this.shifts.filter(s => s.status === 'active').length + ' shift berjalan'},
            ];
        },
        get topProducts() {
            const tally = {};
            this.todayPaid.forEach(o => o.items.forEach(i => { tally[i.product_name] = (tally[i.product_name] || 0) + i.qty; }));
            const rows = Object.entries(tally).map(([name, qty]) => ({name, qty})).sort((a, b) => b.qty - a.qty).slice(0, 5);
            const max = rows[0]?.qty || 1;
            return rows.map(r => ({...r, pct: Math.round(r.qty / max * 100)}));
        },

        // ---------- pesanan ----------
        isPendingCash(o) { return o.payment_method === 'cash' && o.payment_status === 'unpaid' && o.status !== 'cancelled'; },
        get filteredOrders() {
            return this.orders.filter(o => ({
                all: true,
                pending_cash: this.isPendingCash(o),
                paid: o.payment_status === 'paid' && o.status !== 'completed',
                completed: o.status === 'completed',
            })[this.orderFilter]);
        },
        badge(o) {
            if (o.status === 'cancelled') return {text: 'Batal', cls: 'bg-red-50 text-red-600'};
            if (this.isPendingCash(o)) return {text: 'Menunggu tunai', cls: 'bg-amber-50 text-amber-700'};
            if (o.status === 'completed') return {text: 'Selesai', cls: 'bg-stone-100 text-stone-500'};
            if (o.payment_status === 'paid') return {text: 'Lunas · diproses', cls: 'bg-emerald-50 text-emerald-700'};
            return {text: 'Belum bayar', cls: 'bg-stone-100 text-stone-500'};
        },
        async confirmCash(o) {
            const raw = prompt('Uang diterima (kosongkan jika pas):', String(o.total_amount));
            if (raw === null) return;
            const received = raw.trim() === '' ? null : parseInt(raw.replace(/\D/g, ''), 10);
            await this.act(() => this.api(`orders/${o.id}/confirm-cash`, 'POST', {received_amount: received}), 'Pembayaran dikonfirmasi');
        },

        // ---------- meja ----------
        nextTableNumber() { return Math.max(0, ...this.tables.map(t => t.table_number)) + 1; },
        toggleTable(t) { return this.act(() => this.api(`tables/${t.id}`, 'PUT', {is_active: !t.is_active}), 'Meja diperbarui'); },
        regenQr(t) {
            if (!confirm(`Buat QR baru untuk meja ${t.table_number}? QR lama (yang sudah tercetak) tidak akan berlaku lagi.`)) return;
            return this.act(() => this.api(`tables/${t.id}/regenerate-qr`, 'POST'), 'QR baru dibuat');
        },
        removeTable(t) {
            if (!confirm(`Hapus meja ${t.table_number}?`)) return;
            return this.act(() => this.api(`tables/${t.id}`, 'DELETE'), 'Meja dihapus');
        },
        showQr(t) {
            this.qr = t;
            this.$nextTick(() => {
                const box = document.getElementById('qr-box');
                box.innerHTML = '';
                new QRCode(box, {text: t.qr_url, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M});
            });
        },
        downloadQr() {
            const canvas = document.querySelector('#qr-box canvas');
            if (!canvas) return;
            const a = document.createElement('a');
            a.href = canvas.toDataURL('image/png');
            a.download = `qr-meja-${this.qr.table_number}.png`;
            a.click();
        },

        // ---------- staf ----------
        removeStaff(u) {
            if (!confirm(`Hapus akun ${u.name}?`)) return;
            return this.act(() => this.api(`users/${u.id}`, 'DELETE'), 'Akun dihapus');
        },

        // ---------- menu ----------
        get filteredProducts() {
            const q = this.search.trim().toLowerCase();
            return this.products.filter(p => (this.catFilter === 'all' || p.category_id === this.catFilter)
                && (!q || p.name.toLowerCase().includes(q) || (p.category_name || '').toLowerCase().includes(q)));
        },

        // ---- kategori ----
        async addCategory() {
            const name = this.newCategory.trim();
            if (!name) return;
            this.catError = '';
            try { await this.api('categories', 'POST', {name}); this.newCategory = ''; this.toast('Kategori ditambahkan'); await this.reload(); }
            catch (e) { this.catError = e.message; }
        },
        async renameCategory(c, value) {
            const name = value.trim();
            if (!name || name === c.name) return;
            this.catError = '';
            try { await this.api(`categories/${c.id}`, 'PUT', {name}); this.toast('Kategori diperbarui'); }
            catch (e) { this.catError = e.message; }
            await this.reload();
        },
        async removeCategory(c) {
            if (!confirm(`Hapus kategori "${c.name}"?`)) return;
            this.catError = '';
            try { await this.api(`categories/${c.id}`, 'DELETE'); if (this.catFilter === c.id) this.catFilter = 'all'; this.toast('Kategori dihapus'); await this.reload(); }
            catch (e) { this.catError = e.message; }
        },

        // ---- tambah / ubah menu ----
        cloneGroups(groups) {
            return (groups || []).map(g => ({
                name: g.name, kind: g.type === 'variant' ? 'variant' : 'addon',
                addons: g.addons.map(a => ({name: a.name, price: a.price, is_available: a.is_available})),
            }));
        },
        openProduct(p = null) {
            this.pmError = '';
            this.pm = p ? {
                id: p.id, name: p.name, price: p.price, category_id: p.category_id, is_available: p.is_available,
                preview: p.image_url, file: null,
                // grup & pilihan yang sudah ada membawa id supaya riwayat pesanan tetap nyambung
                groups: p.addon_groups.map(g => ({
                    id: g.id, name: g.name, kind: g.type === 'variant' ? 'variant' : 'addon',
                    addons: g.addons.map(a => ({id: a.id, name: a.name, price: a.price, is_available: a.is_available})),
                })),
            } : {id: null, name: '', price: '', category_id: this.catFilter === 'all' ? (this.categories[0]?.id ?? '') : this.catFilter, is_available: true, preview: null, file: null, groups: []};
        },
        pickPhoto(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) { this.pmError = 'Ukuran foto maksimal 2 MB.'; event.target.value = ''; return; }
            this.pmError = '';
            this.pm.file = file;
            this.pm.preview = URL.createObjectURL(file);
        },
        copyOptions(productId) {
            const source = this.products.find(p => p.id === Number(productId));
            if (source) this.pm.groups.push(...this.cloneGroups(source.addon_groups));
        },
        async uploadPhoto(file) {
            const body = new FormData();
            body.append('image', file);
            const res = await fetch('/api/v1/uploads/product-image', {method: 'POST', headers: {Accept: 'application/json', Authorization: 'Bearer ' + this.token}, body});
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.errors?.image?.[0] || json.message || 'Foto gagal diunggah.');
            return json.data.url;
        },
        async saveProduct() {
            const pm = this.pm;
            for (const g of pm.groups) {
                if (!g.name.trim()) { this.pmError = 'Nama grup pilihan wajib diisi.'; return; }
                const filled = g.addons.filter(a => a.name.trim());
                if (!filled.length) { this.pmError = `Grup "${g.name}" butuh minimal satu pilihan.`; return; }
            }
            this.busy = true; this.pmError = '';
            try {
                const payload = {
                    category_id: pm.category_id, name: pm.name.trim(), price: Math.round(Number(pm.price) || 0), is_available: pm.is_available,
                    addon_groups: pm.groups.map(g => {
                        const addons = g.addons.filter(a => a.name.trim()).map(a => ({
                            ...(a.id ? {id: a.id} : {}), name: a.name.trim(), price: Math.round(Number(a.price) || 0), is_available: a.is_available,
                        }));
                        const variant = g.kind === 'variant';
                        return {...(g.id ? {id: g.id} : {}), name: g.name.trim(), is_required: variant, min_qty: variant ? 1 : 0, max_qty: variant ? 1 : addons.length, addons};
                    }),
                };
                if (pm.file) payload.image_url = await this.uploadPhoto(pm.file);
                else if (!pm.id) payload.image_url = 'images/menu-placeholder.png';

                await this.api(pm.id ? `products/${pm.id}` : 'products', pm.id ? 'PUT' : 'POST', payload);
                this.pm = null;
                this.toast('Menu tersimpan');
                await this.reload();
            } catch (e) { this.pmError = e.message; }
            this.busy = false;
        },
        toggleProduct(p) { return this.act(() => this.api(`products/${p.id}`, 'PUT', {is_available: !p.is_available}), 'Menu diperbarui'); },
        removeProduct(p) {
            if (!confirm(`Hapus menu "${p.name}"?`)) return;
            return this.act(() => this.api(`products/${p.id}`, 'DELETE'), 'Menu dihapus');
        },

        // ---------- shift ----------
        forceClose(s) {
            const raw = prompt(`Tutup paksa shift ${s.user_name}. Isi uang kas aktual (Rp):`, String(s.expected_cash ?? 0));
            if (raw === null) return;
            return this.act(() => this.api(`shifts/${s.id}/force-close`, 'POST', {actual_cash: parseInt(raw.replace(/\D/g, ''), 10) || 0}), 'Shift ditutup');
        },

        // ---------- modal form ----------
        open(type, data) { this.modalError = ''; this.modal = {type, data: {...data}}; },
        get modalTitle() {
            if (!this.modal) return '';
            const edit = !!this.modal.data.id;
            return this.modal.type === 'table' ? (edit ? 'Ubah meja' : 'Tambah meja') : (edit ? 'Ubah akun' : 'Tambah akun');
        },
        async submitModal() {
            const {type, data} = this.modal;
            const base = type === 'table' ? 'tables' : 'users';
            const payload = type === 'table'
                ? {table_number: data.table_number}
                : {name: data.name, username: data.username, is_active: data.is_active, ...(data.password ? {password: data.password} : {}), ...(data.id ? {} : {role: data.role})};
            this.busy = true; this.modalError = '';
            try {
                await this.api(data.id ? `${base}/${data.id}` : base, data.id ? 'PUT' : 'POST', payload);
                this.modal = null;
                this.toast('Tersimpan');
                await this.reload();
            } catch (e) { this.modalError = e.message; }
            this.busy = false;
        },

        // ---------- util ----------
        async act(fn, okMsg) {
            try { await fn(); this.toast(okMsg); await this.reload(); }
            catch (e) { this.toast(e.message, true); }
        },
        toast(msg, err = false) {
            this.toastMsg = msg; this.toastErr = err;
            clearTimeout(this._t);
            this._t = setTimeout(() => this.toastMsg = '', 2800);
        },
        rp(n) { return 'Rp' + new Intl.NumberFormat('id-ID').format(n ?? 0); },
        time(iso, withDate = false) {
            if (!iso) return '-';
            const d = new Date(iso);
            const opts = withDate || !this.isToday(iso) ? {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'} : {hour: '2-digit', minute: '2-digit'};
            return d.toLocaleString('id-ID', opts);
        },
    };
}
</script>
</body>
</html>
