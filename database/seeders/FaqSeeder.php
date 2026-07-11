<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'How do I change my selected seat?',
                'answer' => 'Release your current seat hold from the seat map before it expires, then select a different seat. Once a seat is paid for and your booking is confirmed, it can no longer be changed — contact support if you need help.',
                'category' => 'seats',
                'sort_order' => 1,
            ],
            [
                'question' => 'My payment was debited but no receipt',
                'answer' => 'If your payment was debited but you did not receive a receipt, wait a few minutes for the payment provider to confirm the transaction and check your bookings list. If the booking still doesn\'t appear, contact support with your payment reference so we can verify and resolve it.',
                'category' => 'payments',
                'sort_order' => 2,
            ],
            [
                'question' => 'When does the departure manifest close?',
                'answer' => 'The departure manifest closes shortly before the vehicle\'s scheduled departure time. Bookings must be confirmed before this cut-off to guarantee your seat and appear on the manifest.',
                'category' => 'bookings',
                'sort_order' => 3,
            ],
            [
                'question' => 'Can I book for a friend?',
                'answer' => 'Each booking is tied to the verified corps member account making the reservation. To book for someone else, they will need to register and complete the booking from their own account.',
                'category' => 'bookings',
                'sort_order' => 4,
            ],
            [
                'question' => 'What happens if my seat hold expires?',
                'answer' => 'A seat hold reserves your seat for a limited time while you complete payment. If it expires before payment is confirmed, the seat is released back to other users and you\'ll need to select a seat again.',
                'category' => 'seats',
                'sort_order' => 5,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::query()->updateOrCreate(
                ['question' => $faq['question']],
                [...$faq, 'is_published' => true],
            );
        }
    }
}
