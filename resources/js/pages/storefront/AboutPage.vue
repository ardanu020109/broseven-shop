<script setup>
import AppIcon from '@/components/common/AppIcon.vue';
import { useBranding } from '@/composables/useBranding';

const branding = useBranding();
</script>

<template>
    <div class="container" style="max-width: 760px">
        <div class="page-title">
            <h1>Tentang Kami</h1>
            <p>{{ branding.company.store_name }}</p>
        </div>

        <section class="card">
            <p style="white-space: pre-line">
                {{ branding.company.description || 'Toko modern untuk kebutuhan Anda. Pesan dari rumah, ambil dan bayar langsung di kasir.' }}
            </p>

            <h3 style="margin-top: 20px">Hubungi Kami</h3>
            <p v-if="branding.company.address" class="footer__line"><AppIcon name="map-pin" :size="18" /> {{ branding.company.address }}</p>
            <p v-if="branding.company.phone" class="footer__line"><AppIcon name="phone" :size="18" /> {{ branding.company.phone }}</p>
            <p v-if="branding.company.email" class="footer__line"><AppIcon name="mail" :size="18" /> {{ branding.company.email }}</p>
            <p
                v-if="!branding.company.address && !branding.company.phone && !branding.company.email"
                class="muted"
            >
                Informasi kontak belum diatur.
            </p>
        </section>
    </div>
</template>
