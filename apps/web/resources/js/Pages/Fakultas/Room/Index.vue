<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import SampleDataBadge from '@/Components/shared/SampleDataBadge.vue';
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePaginatedList } from '@/composables/useApiList';
import { queryParam } from '@/lib/query';
import { roomClashes, unmappedSlots } from '@/mocks/room';
import type { Room } from '@/types/models';
import { Head } from '@inertiajs/vue3';

const { filters, items: rooms, pagination, page, loading, error } = usePaginatedList<Room, { q: string }>('rooms', { q: queryParam('q') });
</script>

<template>
    <Head title="Alokasi Ruangan" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Alokasi Ruangan</h1>

            <Card class="!p-0 border border-gray-100 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-5 md:flex-row md:items-center md:justify-between">
                    <h2 class="text-sm font-bold text-gray-900">Ruangan Fakultas <span class="ml-1 font-normal text-gray-400">({{ pagination.total }})</span></h2>
                    <input v-model="filters.q" type="search" placeholder="Cari kode, nama, atau gedung..." aria-label="Cari ruangan" class="w-full rounded-xl border-gray-200 bg-gray-50 text-xs placeholder-gray-400 focus:border-primary focus:ring-primary md:w-64" />
                </div>

                <Alert v-if="error" :title="error.message" class="m-5" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-4">Ruangan</th>
                                <th class="px-6 py-4">Gedung &amp; Lantai</th>
                                <th class="px-6 py-4">Kapasitas</th>
                                <th class="px-6 py-4">Pemilik</th>
                                <th class="px-6 py-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs text-gray-700" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && rooms.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">Tidak ada ruangan yang cocok dengan pencarian.</td>
                            </tr>
                            <tr v-for="room in rooms" :key="room.id" class="transition-colors hover:bg-gray-50/60">
                                <td class="px-6 py-4"><span class="font-bold text-gray-900">{{ room.name ?? `Ruang ${room.code}` }}</span><span class="block text-[11px] text-gray-400">{{ room.code }}</span></td>
                                <td class="px-6 py-4">{{ room.building ?? '-' }}<span v-if="room.floor !== null" class="block text-[11px] text-gray-400">Lantai {{ room.floor }}</span></td>
                                <td class="px-6 py-4 font-semibold">{{ room.capacity }} kursi</td>
                                <td class="px-6 py-4">{{ room.faculty?.name ?? 'Ruang Bersama' }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold" :class="room.is_in_use ? 'border-amber-100 bg-amber-50 text-amber-700' : 'border-green-100 bg-green-50 text-green-700'">
                                        {{ room.is_in_use ? 'Digunakan' : 'Tersedia' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="ruangan" />
            </Card>

            <Card class="!p-0 border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 p-5">
                    <h2 class="text-sm font-bold text-gray-900">Bentrok Ruang Antar Prodi <span class="ml-1 font-normal text-gray-400">({{ roomClashes.length }} kasus)</span></h2>
                    <SampleDataBadge />
                </div>
                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-4">Slot Waktu</th>
                                <th class="px-6 py-4">Ruangan</th>
                                <th class="px-6 py-4">Konflik Kelas</th>
                                <th class="px-6 py-4">Rekomendasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr v-for="clash in roomClashes" :key="clash.id">
                                <td class="px-6 py-4 font-semibold">{{ clash.time }}<span class="block text-[11px] font-normal text-gray-400">{{ clash.session }}</span></td>
                                <td class="px-6 py-4 font-bold text-gray-900">{{ clash.room }}</td>
                                <td class="px-6 py-4"><div>{{ clash.existingClass }}</div><div class="font-semibold text-red-600">{{ clash.clashingClass }}</div></td>
                                <td class="px-6 py-4">{{ clash.suggestion }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card class="!p-0 border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 p-5">
                    <h2 class="text-sm font-bold text-gray-900">Slot Belum Terpetakan <span class="ml-1 font-normal text-gray-400">({{ unmappedSlots.length }} slot)</span></h2>
                    <SampleDataBadge />
                </div>
                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-4">Slot Waktu</th>
                                <th class="px-6 py-4">Mata Kuliah</th>
                                <th class="px-6 py-4">Dosen</th>
                                <th class="px-6 py-4">Kebutuhan Ruang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr v-for="slot in unmappedSlots" :key="slot.id">
                                <td class="px-6 py-4 font-semibold">{{ slot.time }}<span class="block text-[11px] font-normal text-gray-400">{{ slot.session }}</span></td>
                                <td class="px-6 py-4 font-bold text-gray-900">{{ slot.course }}</td>
                                <td class="px-6 py-4">{{ slot.lecturer }}</td>
                                <td class="px-6 py-4">{{ slot.need }}<span class="block text-[11px] text-gray-400">{{ slot.capacity }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
