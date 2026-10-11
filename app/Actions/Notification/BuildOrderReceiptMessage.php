<?php

namespace App\Actions\Notification;

use App\Models\Order;

class BuildOrderReceiptMessage
{
    public function handle(Order $order): string
    {
        $order->loadMissing(['branch', 'table', 'items.addons']);

        $lines = [
            "Halo {$order->customer_name}, pembayaran pesananmu sudah kami terima. ✅",
            '',
            "*{$order->branch->name}*",
            "Kode pesanan: *{$order->code}*",
            "Nomor antrean: *{$order->queueNumber()}*",
            'Tipe: '.($order->order_type === 'dine_in'
                ? 'Makan di Tempat'.($order->table ? " · Meja {$order->table->table_number}" : '')
                : 'Bawa Pulang'),
            'Waktu: '.$order->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            '',
            'Pesanan:',
        ];

        foreach ($order->items->whereNull('parent_item_id') as $item) {
            $options = $item->addons->pluck('name')->all();
            $unitPrice = $item->price + $item->addons->sum('price');

            $lines[] = "• {$item->qty}x {$item->product_name}".($options ? ' ('.implode(', ', $options).')' : '');

            $components = $order->items->where('parent_item_id', $item->id)->pluck('product_name')->all();

            if ($components) {
                $lines[] = '   Isi: '.implode(', ', $components);
            }

            if (filled($item->notes)) {
                $lines[] = "   Catatan: {$item->notes}";
            }

            $lines[] = '   '.$this->rupiah($unitPrice * $item->qty);
        }

        $lines[] = '';

        if ($order->tax_amount > 0) {
            $lines[] = 'Pajak: '.$this->rupiah($order->tax_amount);
        }

        $lines[] = '*Total: '.$this->rupiah($order->total_amount).'*';
        $lines[] = 'Metode bayar: '.($order->payment_method === 'qris' ? 'QRIS' : 'Tunai').' (Lunas)';
        $lines[] = '';
        $lines[] = 'Terima kasih sudah memesan! 🙏';

        return implode("\n", $lines);
    }

    private function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
