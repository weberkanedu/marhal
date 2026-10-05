/** "Çıktı al" menüsünün bir satırı (components/ExportMenu.vue). */
export type ExportItem = {
    title: string;
    description?: string;
    // ?format=xlsx|pdf eklenmeden verilir.
    url: string;
    // Yalnız PDF (yaka kartı, koltuk planı): adres olduğu gibi kullanılır.
    pdfOnly?: boolean;
};
