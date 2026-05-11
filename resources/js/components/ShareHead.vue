<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    meta: {
        title: string;
        description: string;
        image: string | null;
        url: string;
        type: string;
        site_name: string;
    };
}>();
</script>

<template>
    <Head>
        <title>{{ meta.title }}</title>
        <meta
            head-key="description"
            name="description"
            :content="meta.description"
        />
        <meta head-key="robots" name="robots" content="noindex, nofollow" />
        <link head-key="canonical" rel="canonical" :href="meta.url" />

        <meta
            head-key="og:type"
            property="og:type"
            :content="meta.type"
        />
        <meta
            head-key="og:title"
            property="og:title"
            :content="meta.title"
        />
        <meta
            head-key="og:description"
            property="og:description"
            :content="meta.description"
        />
        <meta
            head-key="og:url"
            property="og:url"
            :content="meta.url"
        />
        <meta
            head-key="og:site_name"
            property="og:site_name"
            :content="meta.site_name"
        />
        <meta
            head-key="og:locale"
            property="og:locale"
            content="nl_NL"
        />
        <meta
            v-if="meta.image"
            head-key="og:image"
            property="og:image"
            :content="meta.image"
        />

        <meta
            head-key="twitter:card"
            name="twitter:card"
            :content="meta.image ? 'summary_large_image' : 'summary'"
        />
        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="meta.title"
        />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="meta.description"
        />
        <meta
            v-if="meta.image"
            head-key="twitter:image"
            name="twitter:image"
            :content="meta.image"
        />
    </Head>
</template>
