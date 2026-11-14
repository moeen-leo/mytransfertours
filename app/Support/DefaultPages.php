<?php

namespace App\Support;

/**
 * Starting text for the website's information pages. Admins edit the live copy under
 * Setup > Website pages; this text is only used to create the pages and for "Reset".
 *
 * Placeholders, filled in from Setup > Company & invoices when the page is shown:
 * {company_name} {company_email} {company_phone} {company_address} {company_registration} {site_url}
 * {company_details} (name, registration, address, email and phone, one per line)
 *
 * This is a generic template, not legal advice. Have it checked for your business.
 */
class DefaultPages
{
    /** slug => [title, sort, meta description, body (Markdown)] */
    public static function all(): array
    {
        return [
            'about-us' => ['About us', 1, 'Who we are and how we make booking transfers, tours and attraction tickets simple.', self::about()],
            'contact-us' => ['Contact us', 2, 'How to reach our team about a booking or a question.', self::contact()],
            'faq' => ['Frequently asked questions', 3, 'Answers about booking, paying, changes, cancellations and on the day.', self::faq()],
            'terms' => ['Terms and conditions', 4, 'The terms that apply when you book with us.', self::terms()],
            'cancellation-policy' => ['Cancellation and refund policy', 5, 'How cancellations, changes and refunds work.', self::cancellation()],
            'privacy-policy' => ['Privacy policy', 6, 'What personal data we collect, why, and your rights.', self::privacy()],
        ];
    }

    private static function about(): string
    {
        return <<<'MD'
{company_name} makes it easy to plan the practical parts of a trip in one place: **airport and city transfers, day tours and attraction tickets**, all in a single basket and paid once.

## What we do

We work with carefully chosen local transport companies, tour operators and attractions. You choose what you need, see the full price up front, and pay securely online. We pass your booking to the right partner, get it confirmed and send you everything you need by email.

## Why book with us

- **One trip, one payment.** Add a transfer, a tour and tickets to the same booking.
- **Clear prices.** The price you see is the price you pay, with no surprises on the day.
- **Confirmed by real people.** Every booking is checked and confirmed by the local partner who will provide it.
- **Easy to manage.** Change your flight number, hotel or notes online, or contact us and we'll help.
- **Local support.** Our team knows the destinations and is happy to help before and during your trip.

## Where we operate

We are starting in Malaysia and adding more destinations over time.

## Get in touch

Questions about a booking, or planning something special for a group? [Contact us](/contact-us) and we'll be glad to help.
MD;
    }

    private static function contact(): string
    {
        return <<<'MD'
We're here to help before, during and after your trip.

## About an existing booking

The quickest way to see or change a booking is **[Find my booking](/find-booking)**: enter your booking reference (it starts with **MTT-**) and the email address you booked with. You can update flight details, your hotel and notes for the driver online until shortly before the service.

When you contact us, please include your **booking reference** so we can help straight away.

## Before you book

Not sure which vehicle fits your group, or looking for something you can't find on the site? Send us the details (dates, number of travellers and luggage, and where you're going) and we'll suggest the best option.

## Groups and companies

For groups, events or regular bookings for your company, email us and we'll prepare a quote. Companies can also apply for an account with monthly invoicing.
MD;
    }

    private static function faq(): string
    {
        return <<<'MD'
## Booking

**How do I book?**
Choose a transfer, tour or ticket, enter your details and add it to **My trip**. You can add several items and pay for everything at once.

**Do I need an account?**
No. You can book as a guest. An account lets you see all your bookings and invoices in one place, and you can sign in with email or Google.

**Will I get a confirmation?**
Yes. You receive a booking email straight after payment. Each item is then confirmed by the local partner, and we email you when that happens (for transfers this can include the driver's details).

## Prices and payment

**Which currencies can I pay in?**
You can view prices in several currencies. Your card is charged in the currency shown at checkout.

**How do I pay?**
Payment is made by card or other supported methods on a secure Stripe page. We never see or store your full card details.

**Are there extra charges on the day?**
No, the price includes everything listed in the booking, unless you add extras on the day yourself (for example extra waiting time or stops not booked in advance).

## Transfers

**Where will the driver meet me?**
At the airport the driver meets you in the arrivals hall with a name sign. For other pick-ups the driver waits at the address you gave. Exact meeting details are in your confirmation email.

**What if my flight is delayed?**
Add your flight number when you book and the driver will follow your flight. Free waiting time after landing is shown when you book.

**Can I book child seats?**
Yes, child and booster seats can be added as extras when you book.

## Changes and cancellations

**Can I change my booking?**
You can update flight details, hotel and notes online through **[Find my booking](/find-booking)**. For other changes (date, time or vehicle), contact us and we'll do our best to help.

**How do I cancel?**
Contact us with your booking reference. Whether you get a refund depends on the cancellation deadline shown for each item; see our [cancellation and refund policy](/cancellation-policy).
MD;
    }

    private static function terms(): string
    {
        return <<<'MD'
These terms apply to every booking made on {site_url} (the "website"), which is operated by {company_name} ("we", "us"). By making a booking you confirm that you have read and accept them.

## 1. Our role

We arrange transfers, tours and attraction tickets ("services") that are provided by independent local companies ("suppliers"). We act as a booking agent: we take your booking and payment, pass it to the supplier and support you throughout. The supplier is responsible for providing the service. Some suppliers have their own terms, which we will tell you about where they apply.

## 2. Making a booking

- You must be at least 18 years old to book, and the details you give must be accurate and complete.
- The person making the booking accepts these terms on behalf of everyone travelling in the booking.
- A booking is made when payment is completed (or, for approved company accounts, when the booking is confirmed on account). Each item is then confirmed by its supplier.
- If a supplier cannot provide an item, we will offer you an alternative or a full refund for that item.

## 3. Prices and payment

- Prices are shown per vehicle, per person or per ticket as stated, and include the taxes and fees listed. The price is fixed once your booking is made.
- Payments are processed securely by Stripe. Your card is charged in the currency shown at checkout; your bank may apply its own fees or exchange rates.
- Company accounts with credit terms must pay invoices by the due date shown.

## 4. Your responsibilities

- Check your booking details (dates, times, names, flight numbers, pick-up address) and tell us straight away if anything is wrong.
- Be at the meeting point on time. For transfers, please give enough time for your onward journey; we are not responsible for missed flights or connections caused by traffic, weather or other delays outside our control.
- Bring the documents required (for example passports or ID for tickets that need them).
- Follow the reasonable instructions of drivers, guides and venue staff, and respect local laws and customs.

## 5. Changes and cancellations

Changes and cancellations are covered by our [cancellation and refund policy](/cancellation-policy), which forms part of these terms. Each item shows its cancellation deadline when you book and in your confirmation.

## 6. Luggage and passengers

Vehicles are chosen based on the number of passengers and bags you tell us. If you bring more people or luggage than booked, the driver may not be able to take everyone, and extra costs may apply.

## 7. Liability

- We will take reasonable care in arranging your booking. We are not responsible for the acts or omissions of suppliers, but we will help you resolve any problem with them.
- We are not liable for losses caused by events outside our reasonable control, such as extreme weather, natural disasters, strikes, road closures, government actions or health emergencies.
- Nothing in these terms limits liability that cannot be limited by law. Otherwise, our total liability for a booking is limited to the amount you paid for it.

## 8. Complaints

If something goes wrong during a service, please tell the supplier or contact us straight away so we can try to fix it on the spot. Other complaints should be sent to {company_email} within 30 days of the service, with your booking reference.

## 9. Personal data

We use your personal data as described in our [privacy policy](/privacy-policy).

## 10. Changes to these terms

We may update these terms from time to time. The version on the website when you book applies to your booking.

## 11. Governing law

These terms are governed by the laws of Malaysia, and the courts of Malaysia have jurisdiction, unless the law of your country gives you the right to bring a claim in your local courts.

## Contact

{company_details}
MD;
    }

    private static function cancellation(): string
    {
        return <<<'MD'
We know plans change. This policy explains what happens if you need to change or cancel a booking, and what happens if we or a supplier have to.

## Cancellation deadlines

Every item in your booking (transfer, tour or ticket) has its own **cancellation deadline**, shown when you book and in your confirmation email.

- **Cancelled before the deadline:** you receive a **full refund** for that item.
- **Cancelled after the deadline, or no-show:** **no refund**, because the supplier has already reserved the vehicle, guide or tickets for you.
- **Non-refundable tickets:** some attraction tickets cannot be refunded once booked. This is clearly shown before you pay.

To cancel, contact us with your booking reference. The time we receive your request decides whether it is before the deadline.

## Changes

- You can update flight details, your hotel and notes for the driver online through **[Find my booking](/find-booking)** until shortly before the service.
- For other changes (date, time, vehicle or number of travellers), contact us. We'll do our best to help, depending on availability; any price difference will be charged or refunded.

## Delays and missed services

- **Flight delays:** if you added your flight number, the driver follows your flight and waits as described in your booking.
- If you miss a service because you were late to the meeting point or did not show up, it cannot be refunded.

## If we or a supplier cancel

If a service cannot go ahead (for example because of severe weather, safety reasons or a supplier problem), we will offer you an alternative date or service, or a **full refund** for the affected item.

## How refunds are paid

Refunds are made to the original payment method. They are usually processed within 3 working days of being approved, and can then take 5–10 working days to appear, depending on your bank. If you paid in a currency other than your card's, the amount you receive may differ slightly because of exchange rates set by your bank.

## Questions

Contact us at {company_email} with your booking reference and we'll be happy to help.
MD;
    }

    private static function privacy(): string
    {
        return <<<'MD'
This policy explains how {company_name} ("we", "us") collects, uses and protects your personal data when you use {site_url} and book with us. We handle personal data in line with applicable data protection laws, including Malaysia's Personal Data Protection Act 2010.

## Data we collect

- **Booking details:** your name, email address, phone or WhatsApp number, and the details needed for your services, such as travellers, flight numbers, pick-up addresses, hotels and notes.
- **Account details:** if you create an account, your name, email address and password (stored encrypted), or your name, email and profile photo if you sign in with Google.
- **Payment details:** payments are handled by Stripe. We receive confirmation of payment and limited details (such as the card brand and last four digits), but never your full card number.
- **Company details:** for company accounts, billing name, address and tax number.
- **Technical data:** IP address, browser type and basic usage information, kept in server logs for security.

## How we use your data

- To make, manage and confirm your bookings, and to send you confirmations, updates and invoices.
- To share the necessary details with the supplier who provides your service (for example your name, phone number and flight details with your driver).
- To provide customer support and handle changes, cancellations, refunds and complaints.
- To keep the website secure, prevent fraud and meet our legal and accounting obligations.
- To send you news and offers, only if you have agreed to receive them. You can unsubscribe at any time.

## Who we share it with

We only share your data where needed to provide our service:

- **Suppliers** who provide your transfer, tour or tickets.
- **Service providers** that help us run the website: Stripe (payments), Google (sign-in, if you use it), our web hosting company and our email provider.
- **Authorities**, where the law requires it.

We do not sell your personal data. Some of these providers may process data outside Malaysia; where they do, we use providers that protect it to a comparable standard.

## Cookies

We use essential cookies to keep you signed in, remember what is in **My trip** and remember your chosen currency. These are needed for the website to work. We do not use advertising cookies.

## How long we keep it

We keep booking and invoice records for as long as required for accounting and tax purposes (generally 7 years). Account details are kept until you ask us to delete your account. Server logs are kept for a short period for security.

## Your rights

You can ask us to:

- give you a copy of the personal data we hold about you;
- correct data that is wrong or incomplete;
- delete your data, where we are not required to keep it;
- stop sending you marketing messages.

To make a request, email {company_email}. We may need to confirm your identity first, and we will reply within 21 days.

## Security

We protect your data with encryption in transit (HTTPS), encrypted passwords, restricted staff access and secure, reputable service providers.

## Changes

We may update this policy from time to time. The latest version is always on this page.

## Contact

{company_details}
MD;
    }
}
