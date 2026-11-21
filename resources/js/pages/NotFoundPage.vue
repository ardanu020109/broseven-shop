<template>
    <main class="notfound">
        <div class="card notfound__card">
            <h1>404</h1>
            <p>Halaman yang Anda cari tidak ditemukan.</p>
            <RouterLink class="btn btn-primary" to="/">Kembali ke Beranda</RouterLink>
        </div>
    </main>
</template>

<style scoped>
.notfound {
    display: grid;
    min-height: 100vh;
    place-items: center;
    padding: 24px;
}

.notfound__card {
    text-align: center;
}

.notfound__card h1 {
    font-size: 56px;
    color: var(--primary);
}
</style>
