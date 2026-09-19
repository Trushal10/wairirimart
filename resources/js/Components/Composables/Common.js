import { router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

export default function common() {
    let sortKey = '';
    let sortDirection = 'asc';

    const sortTable = (key, data) => {
        if (key === 'action') return data;

        if (sortKey === key) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey = key;
            sortDirection = 'asc';
        }

        const sortedData = [...data].sort((a, b) => {
            let valA = a[key];
            let valB = b[key];

            if (valA == null) valA = '';
            if (valB == null) valB = '';
            
            if (key === 'id') {
                valA = parseInt(valA) || 0;
                valB = parseInt(valB) || 0;
            }

            if (typeof valA === 'string') valA = valA.toLowerCase();
            if (typeof valB === 'string') valB = valB.toLowerCase();

            if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

        return sortedData;
    };
    
    let isFormSubmiting = ref(false);

    const CardInquiryFrom = useForm({
        name: '',
        email: '',
        phone: '',
        message: '',
    });

    function submitCardInquiryForm(url) {
        isFormSubmiting.value = true;
        CardInquiryFrom.errors = {},
        router.post(url, CardInquiryFrom, {
            preserveScroll: true,
            onSuccess: () => {
                CardInquiryFrom.reset();
            },
            onError: (errors) => {
                isFormSubmiting.value = false;
                CardInquiryFrom.errors = errors;
            },
            onFinish: () => {
                isFormSubmiting.value = false;
            },
        });
    }

    /**
     * Normalise a YouTube URL (watch, share, or embed) into an embeddable URL
     * usable in an <iframe src>. Accepts a raw input event OR a string.
     */
    const getVideoUrl = (input) => {
        const raw = typeof input === 'string'
            ? input
            : (input?.target?.value ?? '');
        if (!raw) return '';

        const patterns = [
            /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([A-Za-z0-9_-]{6,})/,
        ];
        for (const rx of patterns) {
            const m = raw.match(rx);
            if (m) return `https://www.youtube.com/embed/${m[1]}`;
        }
        return raw; // fall through — user pasted a custom embed URL
    };

    return {
        sortTable,
        submitCardInquiryForm,
        CardInquiryFrom,
        isFormSubmiting,
        getVideoUrl,
    };
}
