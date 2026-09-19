<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Demo customer reviews for the storefront.
 *
 * Review copy is grouped by product family rather than pinned to product IDs,
 * because IDs differ between environments and the seeded local catalogue is
 * Faker data. Each product is matched to a family by keyword at run time, so
 * this seeder produces sensible copy against the real catalogue too. Anything
 * that matches no keyword falls back to the general gifting pool, which reads
 * correctly for any craft product.
 *
 * Reviewer identities are invented. Emails use the RFC 2606 example.com
 * domain on purpose: a reserved domain cannot reach a real inbox, so demo
 * data can never mail a stranger if someone wires up review notifications.
 *
 * Selection is deterministic — seeded off the product ID — so re-seeding a
 * fresh database yields the same reviews on the same products instead of a
 * different catalogue every run.
 */
class ReviewSeeder extends Seeder
{
    /** How many reviews each product gets (inclusive range). */
    private const MIN_PER_PRODUCT = 3;
    private const MAX_PER_PRODUCT = 7;

    /** Roughly 1 in N is left unapproved so the admin moderation queue isn't empty. */
    private const PENDING_EVERY = 11;

    /** Reviews are dated across this many days back from today. */
    private const SPREAD_DAYS = 300;

    /**
     * Keyword => family. Checked in order, first match wins, so the specific
     * terms must come before the loose ones ('terrazzo tray' is decor, a bare
     * 'tray' is a mould; 'candle holder' is a t-light, 'candle mould' is not).
     */
    private const KEYWORDS = [
        'painting kit'  => 'painting-kits',
        'paint kit'     => 'painting-kits',
        'diy kit'       => 'painting-kits',
        'colouring'     => 'painting-kits',
        'coloring'      => 'painting-kits',

        'candle holder' => 'tlight-holders',
        'tealight'      => 'tlight-holders',
        'tea light'     => 'tlight-holders',
        't light'       => 'tlight-holders',
        't-light'       => 'tlight-holders',
        'tlight'        => 'tlight-holders',
        'votive'        => 'tlight-holders',
        'diya'          => 'tlight-holders',

        'planter'       => 'home-decor',
        'flower pot'    => 'home-decor',
        'succulent'     => 'home-decor',
        'terrazzo'      => 'home-decor',
        'vase'          => 'home-decor',

        'mould'         => 'silicone-moulds',
        'mold'          => 'silicone-moulds',
        'silicone'      => 'silicone-moulds',
        'cavity'        => 'silicone-moulds',
        'tray'          => 'silicone-moulds',

        'concrete'      => 'home-decor',
        'plaster'       => 'home-decor',
        'decor'         => 'home-decor',
        'pot'           => 'home-decor',
    ];

    private const FALLBACK_FAMILY = 'gifting-bulk';

    public function run(): void
    {
        $products = Product::query()->orderBy('id')->get(['id', 'name', 'short_description']);

        if ($products->isEmpty()) {
            $this->command?->warn('ReviewSeeder skipped - no products to attach reviews to.');

            return;
        }

        // Never touch a product that already has reviews. On production those
        // are real customers, and demo copy must not be mixed in with them.
        // This is also what makes the seeder safe to re-run: a second pass
        // adds nothing rather than doubling every product's reviews.
        $alreadyReviewed = Review::query()->distinct()->pluck('product_id')->flip();

        // Tracks how many products have already drawn from each family, so
        // consecutive products of the same type start at different points in
        // the pool instead of all showing the first three reviews.
        $drawn = [];
        $rows = [];
        $skipped = 0;

        foreach ($products as $product) {
            if ($alreadyReviewed->has($product->id)) {
                $skipped++;

                continue;
            }

            $family = $this->familyFor($product);
            $pool = self::POOLS[$family];
            $size = count($pool);

            $seat = $drawn[$family] ?? 0;
            $drawn[$family] = $seat + 1;

            $count = self::MIN_PER_PRODUCT
                + (($product->id * 7) % (self::MAX_PER_PRODUCT - self::MIN_PER_PRODUCT + 1));
            $start = ($seat * 5 + $product->id) % $size;

            for ($k = 0; $k < $count; $k++) {
                // Stride 3 is coprime with every pool size (14, 16, 20), so a
                // product never draws the same review twice.
                $entry = $pool[($start + $k * 3) % $size];

                $daysAgo = 4 + (($product->id * 13 + $k * 29) % self::SPREAD_DAYS);
                $createdAt = Carbon::now()
                    ->subDays($daysAgo)
                    ->setTime(9 + (($product->id + $k) % 12), (($product->id * 17 + $k * 41) % 60));

                $rows[] = [
                    'product_id'  => $product->id,
                    'customer_id' => null,
                    'rate'        => $entry['rate'],
                    'title'       => $entry['title'],
                    'review'      => $entry['review'],
                    'name'        => $entry['name'],
                    'email'       => Str::slug($entry['name'], '.') . '@example.com',
                    'image'       => null,
                    'is_approved' => (($product->id + $k) % self::PENDING_EVERY) !== 0,
                    'is_spam'     => false,
                    'created_at'  => $createdAt,
                    'updated_at'  => $createdAt,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Review::insert($chunk);
        }

        $approved = count(array_filter($rows, static fn ($r) => $r['is_approved']));
        $this->command?->info(sprintf(
            'Seeded %d reviews across %d products (%d approved, %d awaiting moderation).',
            count($rows),
            $products->count() - $skipped,
            $approved,
            count($rows) - $approved
        ));

        if ($skipped > 0) {
            $this->command?->warn(sprintf(
                '%d product(s) left untouched - they already had reviews.',
                $skipped
            ));
        }
    }

    /**
     * Pick a review family from the product's name and short description.
     */
    private function familyFor(Product $product): string
    {
        $haystack = Str::lower(trim(($product->name ?? '') . ' ' . strip_tags((string) $product->short_description)));

        foreach (self::KEYWORDS as $needle => $family) {
            if (str_contains($haystack, $needle)) {
                return $family;
            }
        }

        return self::FALLBACK_FAMILY;
    }

    /**
     * Review copy, grouped by product family.
     */
    private const POOLS = [
        'silicone-moulds' => [
            [
                'rate'   => 5,
                'title'  => 'Tree of life came out sharp',
                'name'   => 'Priya Deshmukh',
                'review' => 'Cast it in white cement and every branch line came out clear, demoulded in one go without '
                    . 'tearing.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Running my resin orders on this',
                'name'   => 'Lakshmi Narayanan',
                'review' => 'I make resin coasters for my Instagram page and this tray has already given me around 30 '
                    . 'pours. The cavities are deep enough that I get proper 8mm thickness without doing a second '
                    . 'layer. Silicone is flexible so the pieces pop out with a small push from underneath, no '
                    . 'chipping on the edges. One thing I do is wipe it with isopropyl before each pour, otherwise '
                    . 'dust settles in the corners. No yellow staining yet even after using a strong red pigment. '
                    . 'For bulk work it pays for itself quickly.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Neat petals, floppy base',
                'name'   => 'Ananya Chatterjee',
                'review' => 'Used it with plaster of paris and the petal edges came out very neat, only the base needed a '
                    . 'light sanding. The walls of the mould are on the thinner side though, so it bends if you '
                    . 'dont keep it on a flat tray while setting. Otherwise no complaints, the finished pieces are '
                    . 'sitting in my puja room now.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Bubble cube candles turned out lovely',
                'name'   => 'Hetal Patel',
                'review' => 'Poured soy wax at around 65 degrees and the mould handled it perfectly, no odd smell and no '
                    . 'change in shape after four batches. The bubbles are evenly formed and I could unmould after '
                    . '20 minutes in the fridge. Second one is already on the way.',
            ],
            [
                'rate'   => 3,
                'title'  => 'Detail is nice but shallow',
                'name'   => 'Simran Kaur',
                'review' => 'The mandala pattern is really pretty and it holds detail well in resin. But the cavity is '
                    . 'shallow, my piece came out barely 5mm thick and it feels flimsy as a coaster. There was a '
                    . 'faint rubber smell for the first two days also, that went after a wash.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Survived my concrete mixes',
                'name'   => 'Aravind Menon',
                'review' => 'I cast in concrete which is much rougher on silicone than resin, so I expected the mould to '
                    . 'give up in a few uses. Around 15 casts done and there is no tearing at the corners yet. The '
                    . 'surface finish is smooth enough that I only need a light rub with sandpaper on the back '
                    . 'side. Cleaning is easy also, I let the leftover cement dry fully and peel it off instead of '
                    . 'scrubbing. Took 4 days to reach Kochi and the mould came rolled in a foam sheet, no creases '
                    . 'in it.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Soap petals came out crisp',
                'name'   => 'Divya Reddy',
                'review' => 'Made melt and pour soaps with the flower mould, petals came out crisp and it releases '
                    . 'without any tugging. very happy with this.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Keychain size, not coaster size',
                'name'   => 'Nusrat Sheikh',
                'review' => 'The heart cavities are cute and the finish is smooth, but they are much smaller than the '
                    . 'photo suggested, more keychain size than coaster size. For resin keychains it actually suits '
                    . 'me so I am keeping it. Silicone is thick and bendy, that part is good.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Pops out in one push',
                'name'   => 'Meghna Barua',
                'review' => 'scalloped edges come out clean and the piece pops out with one push, no release spray '
                    . 'needed.',
            ],
            [
                'rate'   => 2,
                'title'  => 'Warped after a hot pour',
                'name'   => 'Kavitha Shetty',
                'review' => 'I poured a thick resin layer, it heated up a lot while curing and now the base of the mould '
                    . 'has a permanent dip in it. Every casting after that comes out uneven on one side, so for '
                    . 'deep work the mould is finished as far as I am concerned. The pattern detail is genuinely '
                    . 'good, the silicone is just too soft to take that much heat.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Chocolates release clean',
                'name'   => 'Rohit Sawant',
                'review' => 'Bought the round cavity tray mainly for chocolate and it does the job, the pieces release '
                    . 'cleanly straight from the fridge and the cavities are all the same size. Delivery dragged to '
                    . '6 days for Nashik when checkout showed less, that was the annoying part. No smell from the '
                    . 'silicone at least, which matters when you are putting food in it.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Sixty casts in two days',
                'name'   => 'Sharmila Pillai',
                'review' => 'We had a function at home and I needed around 60 small pieces to hand out, so I bought two '
                    . 'lotus moulds and cast them in plaster of paris. Each set took about 40 minutes to firm up so '
                    . 'I could finish many rounds in a day. The petals hold their detail even after repeated use '
                    . 'and both moulds still look new after washing. My tip is to tap the mould well after pouring, '
                    . 'otherwise small bubbles sit in the petal tips. A few guests thought the pieces were shop '
                    . 'bought.',
            ],
            [
                'rate'   => 3,
                'title'  => 'Stained with alcohol ink',
                'name'   => 'Jyoti Rathore',
                'review' => 'The mould works fine and the flower shape is detailed. After using alcohol ink in resin the '
                    . 'silicone has picked up a blue tint that is not going even with soap. It does not transfer to '
                    . 'the new castings so far, but the mould looks well used after only 5 pours.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Mandala lines show up',
                'name'   => 'Elizabeth Thomas',
                'review' => 'Every small line in the mandala comes through in the cast, even the thin ones near the '
                    . 'centre.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Small seam line to file',
                'name'   => 'Pooja Agarwal',
                'review' => 'The square cavities give a sharp edge in concrete and demoulding is easy once it is fully '
                    . 'cured. There is a faint seam line running down one side of the mould, so every piece needs a '
                    . 'minute of filing on that edge before I can paint it. It came flat in a stiff envelope with '
                    . 'card inside, no bending in transit.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Handles hot wax well',
                'name'   => 'Sanjana Naik',
                'review' => 'Poured paraffin much hotter than I should have by mistake and the mould did not deform at '
                    . 'all. The candles come out with clean bubble shapes and nothing sticks inside, one wipe with '
                    . 'tissue is enough. Two months of use and it looks the same as day one.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Repeat buyer for my stall',
                'name'   => 'Imran Qureshi',
                'review' => 'This is my third order from here for my little cement decor business. The tree of life mould '
                    . 'is the one I use most, it gives around 20 to 25 casts before the fine lines start softening, '
                    . 'which I think is fair at this rate. The silicone also stays flexible in winter, some cheaper '
                    . 'moulds I bought earlier went stiff and split at the corner. I finish the pieces with a gold '
                    . 'rub and they clear out fast at exhibition stalls. Clubbing two moulds into one order gets me '
                    . 'past the 999 mark for free shipping as well.',
            ],
            [
                'rate'   => 3,
                'title'  => 'Grooves are hard to clean',
                'name'   => 'Ritika Sen',
                'review' => 'Casting quality is decent, the hearts come out well formed in resin. My problem is the '
                    . 'narrow grooves trap dust and dried resin bits and I am picking them out with a toothpick '
                    . 'every single time. One cavity is also slightly deeper than the rest so my sets never look '
                    . 'uniform.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Needed a wash first',
                'name'   => 'Vaishnavi Iyer',
                'review' => 'detail retention in resin is excellent, but mine came coated in some powder and needed two '
                    . 'washes before the first pour.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Kids managed it themselves',
                'name'   => 'Merenla Jamir',
                'review' => 'Bought the flower and heart cavity moulds for a summer activity with my two children. We '
                    . 'melted glycerin soap base and poured it in, and even they could demould it without help '
                    . 'which surprised me. The silicone is soft and washes clean with warm water. Ten or so uses '
                    . 'later there are still no tears at the edges. It reached Dimapur in five days which I '
                    . 'honestly did not expect.',
            ],
        ],

        'painting-kits' => [
            [
                'rate'   => 5,
                'title'  => 'Good rainy day activity',
                'name'   => 'Aarti Wagh',
                'review' => 'Six paint pots, three brushes and the plain piece, my daughter was busy the whole rainy '
                    . 'afternoon. Value for money.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Sixty boxes for a birthday',
                'name'   => 'Revathi Sundaram',
                'review' => 'I ordered these kits in bulk for my daughters birthday, 60 pieces in all. Every box came '
                    . 'sealed with the paint pots, two brushes and the plain cast piece inside, and only one had a '
                    . 'small chip on the base. The children actually sat down and painted for almost an hour, which '
                    . 'never happens at these parties. Two mothers messaged me the next day asking where I had '
                    . 'picked them up from. Each kit sat in its own slot in the outer carton so nothing rattled '
                    . 'around. The Diwali order will be going in soon.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Paint pots run out fast',
                'name'   => 'Moushumi Ganguly',
                'review' => 'The kit is nice and the acrylic colours are quite bright, two coats covered the plaster '
                    . 'piece fully. The pots are tiny though, the white finished while I was still doing the base '
                    . 'coat and I had to top up from my own tube. A bigger pot for white and black would make a '
                    . 'real difference.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Kept my son busy',
                'name'   => 'Rutvi Trivedi',
                'review' => 'Took this for my son during his summer holidays. Pigment is strong so he did not need many '
                    . 'coats, and the brushes suited a childs hand. He spent about 45 minutes on it and the '
                    . 'finished piece is in our showcase now.',
            ],
            [
                'rate'   => 3,
                'title'  => 'Cast piece had a crack',
                'name'   => 'Harpreet Ahluwalia',
                'review' => 'The paints and brushes are okay, nothing wrong there, the colours mix well and dry fast. But '
                    . 'the cast piece in my box had a hairline crack near the base and one corner was chipped. I '
                    . 'sanded it lightly and painted over it so it is not very visible, but for something meant to '
                    . 'be handed over as a gift this should not happen. The outer carton was in good shape, so I '
                    . 'think the piece was already damaged when it went in. Three stars only because the paint side '
                    . 'of it was genuinely good.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Brushes were soft',
                'name'   => 'Neethu Varghese',
                'review' => 'kids finished it in one sitting, brushes were soft and the colours came out bright for what '
                    . 'it costs.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Thin brush shed bristles',
                'name'   => 'Sai Kiran Vemula',
                'review' => 'Colours are well pigmented and my niece finished her piece in about half an hour. The thin '
                    . 'brush started shedding bristles into the wet paint after ten minutes and we had to pick them '
                    . 'out and switch to our own brush. Decent kit otherwise at this rate.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Filled a full class session',
                'name'   => 'Deepa Hebbar',
                'review' => 'I take a small weekend art class for 8 to 12 year olds and ordered 12 kits to try out. Every '
                    . 'box had the same set, six colour pots, two brushes, the blank casting and a small '
                    . 'instruction slip which the children read on their own. Pigment strength is good, one coat '
                    . 'was enough on most shades except yellow. The quick ones finished in 40 minutes and the '
                    . 'slower children took over an hour, so it filled the whole session comfortably. It reached '
                    . 'Mangaluru in four days and a bigger order is going in next month.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Sunday evening with my sister',
                'name'   => 'Tanvi Sharma',
                'review' => 'Did this with my sister on a Sunday evening, very relaxing. The paint went on smoother than '
                    . 'I was expecting.',
            ],
            [
                'rate'   => 4,
                'title'  => 'No instruction slip inside',
                'name'   => 'Farhana Ansari',
                'review' => 'Kit is good, the blank piece was smooth and clean with no chips and the paint grips without '
                    . 'a primer. My box came without the instruction slip, so I was guessing the drying time '
                    . 'between coats. Not a problem for me, but a child opening this on their own would be a bit '
                    . 'lost.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Reached Guwahati intact',
                'name'   => 'Bornali Saikia',
                'review' => 'Nice kit, took 5 days to reach Guwahati and everything was intact, my daughter enjoyed '
                    . 'painting hers.',
            ],
            [
                'rate'   => 2,
                'title'  => 'Half the paints had dried',
                'name'   => 'Madhavi Kulkarni',
                'review' => 'Three of the six pots had dried into a hard cake, and when I added water the colour went '
                    . 'patchy and streaky on the piece. The brushes and the cast piece were fine, so it is only the '
                    . 'paints that let it down. This was meant for my nephews birthday and I ended up buying '
                    . 'acrylics separately at the last minute, which defeats the whole point of a ready kit.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Bought two for the twins',
                'name'   => 'Arjun Sasidharan',
                'review' => 'Ordered two kits for my twins so there would be no fighting over one. Both boxes were '
                    . 'complete and identical and no paint had leaked in transit. They were at it the whole evening '
                    . 'and the pieces look quite presentable even with kid level painting.',
            ],
            [
                'rate'   => 3,
                'title'  => 'Three coats for full cover',
                'name'   => 'Piyali Mandal',
                'review' => 'The box looks good and it does present well as a gift. But the paint is thin, it took three '
                    . 'coats to cover the grey plaster and the lighter shades still looked patchy in places. '
                    . 'Brushes were fine, so it is really the paint holding this kit back.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Sourcing these for my gift page',
                'name'   => 'Karthika Balan',
                'review' => 'I run a small gifting business and have been sourcing these kits for the last two months, '
                    . 'mostly for orders that come through my WhatsApp catalogue. Customers like that everything '
                    . 'sits in one box and they dont have to go hunting for paints separately. The castings have '
                    . 'been consistent, out of around 40 pieces only two had a rough edge and that sanded down in a '
                    . 'minute. Bulk packing is sturdy, the kits come stacked with card between the layers. Repeat '
                    . 'orders are coming in from the same parents, so the children are clearly enjoying them. One '
                    . 'suggestion, put in a small sheet with a few painting ideas for the younger ones.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Outer box got dented',
                'name'   => 'Jaspreet Singh Bhatia',
                'review' => 'Gave this to my friends daughter for her birthday and she sat and painted it happily. The '
                    . 'contents were fine but the outer box is flimsy, one edge had caved in by the time it reached '
                    . 'even though the courier packing was intact. Nothing inside was damaged, but the box does '
                    . 'matter when you are handing it over as a gift.',
            ],
        ],

        'tlight-holders' => [
            [
                'rate'   => 5,
                'title'  => 'Third order of the terrazzo',
                'name'   => 'Sneha Chavan',
                'review' => 'This is my third order of the terrazzo tealight holders for my decor page on Instagram. The '
                    . 'white ones with black and rust speckles match the listing photos, and the pattern falls '
                    . 'differently on every piece which my customers actually like. They have enough weight that '
                    . 'they dont slide around when I am styling a shoot. I only wish there was a set of 8, six is '
                    . 'never enough once I start building a hamper. Eighteen pieces have come through so far, each '
                    . 'one padded on its own, and not a single chip.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Chip on one rim',
                'name'   => 'Nithya Ramanathan',
                'review' => 'Ordered 8 lotus holders to hand out at a house function. The petals are neatly finished and '
                    . 'the tealight sits in snugly without slipping. One piece had a chip on the bottom rim, not '
                    . 'visible once it is placed down, but the edges could be padded better.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Striped shadows on the wall',
                'name'   => 'Ritwika Dasgupta',
                'review' => 'The ribbed concrete ones throw a lovely striped shadow up the wall when lit, two of them '
                    . 'changed my whole dining table setting. The finish is smooth and no cement dust rubs off on '
                    . 'the table. Two more are going into my next order for the side table.',
            ],
            [
                'rate'   => 5,
                'title'  => 'No wobble on the table',
                'name'   => 'Krupa Prajapati',
                'review' => 'Bought 6 for Diwali, all reached safely and they have enough weight that there is no '
                    . 'wobbling. Worth the price.',
            ],
            [
                'rate'   => 3,
                'title'  => 'Chalky base left marks',
                'name'   => 'Manpreet Sethi',
                'review' => 'The fluting on these is nice and they do look elegant on my console table. But they are '
                    . 'plaster and the base kept leaving a white powdery ring on the wood, so now every one of them '
                    . 'sits on a coaster. Soot also built up around the rim after two evenings and it does not wipe '
                    . 'off fully.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Favourite corner of the house',
                'name'   => 'Anjali Kurup',
                'review' => 'I bought the floral printed set of 4 for my pooja room and that corner has become my '
                    . 'favourite spot in the house. The painting on the outside is neat with no smudging at the '
                    . 'petal edges, and the mustard and green came through just as I had hoped. With all four lit '
                    . 'together the light coming off the white inside is very soft, much nicer than the metal diyas '
                    . 'I was using earlier. They are plaster so a bit light in the hand, but on a shelf that hardly '
                    . 'matters. Reached Thrissur in three days, each piece in its own paper sleeve.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Fewer speckles than shown',
                'name'   => 'Lavanya Bandaru',
                'review' => 'Sturdy holders, the concrete is properly cured and there is no chipping at the edges. The '
                    . 'terrazzo speckles are far fewer than the listing shows though, mine are close to plain white '
                    . 'with a few black dots here and there. Still looks decent when lit but I had picked them for '
                    . 'the colour.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Six around the mantap',
                'name'   => 'Shruthi Gowda',
                'review' => 'Kept 6 of these around the Ganpati mantap this year and half the people who came asked me '
                    . 'where they were from. The lotus shape holds the tealight snugly and even after three hours '
                    . 'of burning the holder was only slightly warm to touch. Came in two days to Bengaluru.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Pretty when the candle is lit',
                'name'   => 'Nayanika Hazarika',
                'review' => 'lotus holder looks really pretty once the candle is lit and it came quicker than the date '
                    . 'shown.',
            ],
            [
                'rate'   => 2,
                'title'  => 'Two cracked on arrival',
                'name'   => 'Farheen Mirza',
                'review' => 'The set of 4 concrete holders came on time but two had hairline cracks near the rim and a '
                    . 'third has an uneven base, so it rocks whenever the table is nudged. One of the cracked ones '
                    . 'came apart in my hand while I was rinsing the dust off. All four were wrapped together in a '
                    . 'single layer with nothing in between, which is not enough for something this heavy. The two '
                    . 'good pieces are genuinely nice and the glow through them is warm, otherwise this would have '
                    . 'been a one star. Kindly put dividers between the pieces, they are breakable.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Six centimetres across',
                'name'   => 'Merlyn D\'Souza',
                'review' => 'Took 12 pieces for my sisters wedding table decor and they did the job, guests were picking '
                    . 'them up to look at the finish. They are on the smaller side, roughly 6 cm across, so on a '
                    . 'long table you need a lot of them to make any impact. The size in cm should be on the '
                    . 'listing itself.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Two months on the balcony',
                'name'   => 'Kaustubh Deshpande',
                'review' => 'Bought a pair of the ribbed ones for our balcony table. Two months of Pune weather including '
                    . 'a good amount of rain and the finish has not chipped or faded. They are heavy enough that '
                    . 'the wind does not push them about, which is more than I can say for the ceramic ones I had '
                    . 'earlier.',
            ],
            [
                'rate'   => 3,
                'title'  => 'Coating flaked off',
                'name'   => 'Karthik Subramanian',
                'review' => 'Shape wise these are good, the fluting is even and in person they look like the pictures. My '
                    . 'issue is the finish. Within a week the coating on the outer edge of two holders started '
                    . 'coming away in small flakes, right at the spot where you hold it while lighting. The base is '
                    . 'rough as well and left thin scratch lines on my glass table. No complaint on the glow or the '
                    . 'shape, but the finish quality is only worth three stars.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Soot wipes off easily',
                'name'   => 'Nilanjan Dutta',
                'review' => 'Solid holders and the soot around the rim comes off with a dry cloth, no stain left behind.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Tealight sits a bit proud',
                'name'   => 'Preeti Nagpal',
                'review' => 'Reached in 5 days and packed well, no damage anywhere. The well is a little shallow though, '
                    . 'a standard tealight sits slightly above the rim instead of sinking in, so from the side you '
                    . 'can see the metal cup. Fine from above, just not what I had pictured.',
            ],
            [
                'rate'   => 5,
                'title'  => 'All four intact',
                'name'   => 'Ishita Bhardwaj',
                'review' => 'Set of 4 ribbed holders and not one crack in the lot. The warm glow at night is exactly what '
                    . 'I was after.',
            ],
        ],

        'home-decor' => [
            [
                'rate'   => 5,
                'title'  => 'Snake plant sits well',
                'name'   => 'Vrushali Jadhav',
                'review' => 'Ordered two arch ribbed planters for my balcony shelf and both came padded properly in the '
                    . 'carton. The ribs are sharp and even with no chipping on the rim. There is a snake plant in '
                    . 'the bigger one and a jade in the smaller. The drainage hole is already there at the bottom, '
                    . 'which not every seller bothers with. They are heavy, so once you place them you are not '
                    . 'shifting them around every week. The same pair is going to my sister next month.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Haworthias look nice in these',
                'name'   => 'Kalpana Srinivasan',
                'review' => 'Small succulent pots arrived safely, the finish is smooth inside and out and my haworthias '
                    . 'look really nice in them.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Came covered in dust',
                'name'   => 'Anirban Roy',
                'review' => 'The terrazzo piece is well made and the surface has been sanded properly, no rough patches '
                    . 'anywhere. It arrived with a layer of fine white dust over it though, and it took a damp '
                    . 'cloth and about ten minutes to get it out of the speckled grooves. Looks smart on my study '
                    . 'desk now that it is clean.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Fits my pilea exactly',
                'name'   => 'Bhavika Pandya',
                'review' => 'Bought the small concrete pot for my work from home table, it is exactly the 3 inch listed '
                    . 'and my pilea sits in it perfectly.',
            ],
            [
                'rate'   => 3,
                'title'  => 'Left a ring on my shelf',
                'name'   => 'Gurleen Sodhi',
                'review' => 'The planter is nicely finished and I like the shape of it. But moisture comes through the '
                    . 'concrete after watering and it left a pale ring on my wooden shelf within a week. A coaster '
                    . 'underneath sorts it out, but nowhere does it say the pot needs sealing before use. Good '
                    . 'looking piece, spoiled by something that should have been mentioned in the description.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Heavy but base scratches',
                'name'   => 'Anju Mathew',
                'review' => 'Good solid planter, it has real weight to it and none of that hollow lightweight feel. The '
                    . 'underside is bare concrete and it scraped my sideboard the first time I turned it around, so '
                    . 'I have stuck felt pads on now. Would be an easy thing for them to add at their end.',
            ],
            [
                'rate'   => 4,
                'title'  => 'White patch after watering',
                'name'   => 'Saipriya Kondapalli',
                'review' => 'Quality wise it is well made, the ribbing is neat and there is a proper drainage hole with a '
                    . 'mesh in it. One side of the pot has developed a chalky white patch after a couple of weeks '
                    . 'of watering, which I know happens with cement but it really shows on the darker grey. '
                    . 'Scrubbing lightens it and then it comes back.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Six pieces to test',
                'name'   => 'Chaitra Hegde',
                'review' => 'I style and sell plants at a weekend market stall and ordered six pieces to test - two arch '
                    . 'planters, two ribbed pots and two terrazzo trays. Everything came out of the carton intact, '
                    . 'the wrapping was generous for the weight. The terrazzo ones went first, the white base with '
                    . 'black and rust chips photographs beautifully in daylight. Four days to Hubballi for '
                    . 'something this heavy seems fair to me. A bulk order is going in before the festive season.',
            ],
            [
                'rate'   => 2,
                'title'  => 'Crack down the side',
                'name'   => 'Lalthanpuii Sailo',
                'review' => 'The bigger arch planter came with a hairline crack running from the rim down one side. The '
                    . 'outer carton was not damaged at all, so either it went in like that or one layer of wrapping '
                    . 'was never going to be enough for something this heavy. The smaller pot in the same order is '
                    . 'perfectly fine and I do like the design. Right now I have a planter I cannot put soil into, '
                    . 'which is a poor result for what I paid.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Three pots as a gift',
                'name'   => 'Nazia Hussain',
                'review' => 'Took three succulent pots along to a friends new flat and they went down very well. Neat '
                    . 'finish, no rough edges anywhere, and the muted grey went with the rest of her shelf. '
                    . 'Delivery was quick, three days.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Darker grey in person',
                'name'   => 'Ashwin Fernandes',
                'review' => 'Build quality is solid, thick concrete and a proper drainage hole with a mesh already '
                    . 'fitted. The grey is much darker than the website photo, almost charcoal. I had planned it '
                    . 'for a dim corner and had to move it near the window instead, not the end of the world but '
                    . 'still a change of plan.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Balcony looks better now',
                'name'   => 'Meenakshi Ganesan',
                'review' => 'two ribbed planters on the balcony shelf and the whole corner looks different, very nice '
                    . 'pieces.',
            ],
            [
                'rate'   => 4,
                'title'  => 'No drainage hole',
                'name'   => 'Nikhil Bhosale',
                'review' => 'Decent terrazzo pot but there is no drainage hole, I had to drill one myself with a masonry '
                    . 'bit and it chipped slightly at the back. The description does not say this clearly anywhere, '
                    . 'so do check before you order it for a live plant. The speckled finish is lovely and the base '
                    . 'does not scratch my table.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Keys and earrings bowl',
                'name'   => 'Tarini Bansal',
                'review' => 'The terrazzo bowl is my favourite thing on the shelf at the moment, keys and earrings go '
                    . 'into it every evening. The speckling is random on every piece and mine has more black chips '
                    . 'than the one pictured, which I liked. It is heavy, but that means it stays put instead of '
                    . 'sliding about.',
            ],
        ],

        'gifting-bulk' => [
            [
                'rate'   => 5,
                'title'  => 'Hundred holders, none chipped',
                'name'   => 'Shubhangi Gokhale',
                'review' => 'Ordered 100 lotus tealight holders for my daughters wedding and they came with cardboard '
                    . 'dividers between the layers, every piece came out of the box intact. The concrete finish was '
                    . 'consistent across the whole lot, only two had a faint shade difference and nobody noticed '
                    . 'once the tealights were lit. Per piece it worked out far cheaper than what the gift shops '
                    . 'near Camp were quoting for anything half decent. We kept one on every table during the '
                    . 'sangeet and people kept asking where they were from. Six days for an order that size seems '
                    . 'reasonable to me.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Cheaper than I budgeted',
                'name'   => 'Aishwarya Rajagopal',
                'review' => 'ordered 30 mini succulent pots for a house function, all 30 came through without a scratch '
                    . 'and it cost me less than half of what I had set aside.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Two shades of white',
                'name'   => 'Rituparna Ghosh',
                'review' => 'Took 45 terrazzo tealight holders for our Puja committee gifting. The speckled finish looks '
                    . 'premium, but the batch came in two slightly different whites so we had to sort them into two '
                    . 'sets before packing, which was an hour we had not planned for. Nothing broke and they '
                    . 'reached us before Panchami.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Sixty for the hampers',
                'name'   => 'Nirali Panchal',
                'review' => 'Bought 60 ribbed tealight holders for our Diwali hampers. They arrived padded individually '
                    . 'and lit up along the rangoli they looked really beautiful. It kept the hamper budget in '
                    . 'control, which was the main thing for me.',
            ],
            [
                'rate'   => 3,
                'title'  => 'Half the batch needed sanding',
                'name'   => 'Kiranjeet Sandhu',
                'review' => 'I ordered 50 plaster lotus holders for a baby shower and it was a mixed bag honestly. About '
                    . '30 were perfect, crisp petals and a smooth flat base. The rest had small pinholes on one '
                    . 'side and two had a rough ridge where the mould line sits, so I spent a full evening sanding '
                    . 'and touching them up with white paint before they could be packed. For a bulk order I '
                    . 'expected more consistency piece to piece. Nothing broke in transit, that is the only reason '
                    . 'this is not a two.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Twenty five identical pieces',
                'name'   => 'Sujatha Nair',
                'review' => 'Took 25 pieces for a baby shower and every single one looked identical, neatly packed and '
                    . 'here in 4 days.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Lead time hurt me',
                'name'   => 'Sravanthi Kandula',
                'review' => 'I paint tealight holders and sell the finished sets, and I buy the blanks here in lots of '
                    . '40. They are consistent enough that a finished set looks uniform when I photograph it, and '
                    . 'after paint and packing material the margin still works for me. The lead time is the '
                    . 'problem, my last lot took 9 days when I had already promised dates to my own customers.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Third bulk order',
                'name'   => 'Roopashree Bhat',
                'review' => 'This is my third bulk order with them, 75 pieces this time, a mix of arch ribbed planters '
                    . 'and small concrete pots. Consistency matters to me because I shoot the whole lot as one set '
                    . 'for my catalogue, and the sizing has held up across all three orders. Nothing has ever '
                    . 'reached me broken, the gaps in the carton are filled properly instead of letting the pieces '
                    . 'knock together. The per piece cost lets me price at a point my customers accept and still '
                    . 'earn something after courier. They were also fine with me splitting the quantity across two '
                    . 'designs.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Kits for the school fete',
                'name'   => 'Bandana Deka',
                'review' => '20 painting kits for our school fete stall, every box sealed and complete, and the children '
                    . 'cleared them out in under an hour.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Loose brushes in two boxes',
                'name'   => 'Shabana Pathan',
                'review' => 'Ordered 35 DIY painting kits as return gifts for my sons birthday and the children were busy '
                    . 'painting for a good one hour. Two kits had the brush rolling around loose instead of held in '
                    . 'place, and in one of them a pot lid had come off and smeared colour inside the box. Still '
                    . 'reasonable value for a return gift at this rate.',
            ],
            [
                'rate'   => 2,
                'title'  => 'Seven chipped rims',
                'name'   => 'Merin Jacob',
                'review' => 'I ordered 40 plaster tealight holders for a church charity sale and 7 arrived with chipped '
                    . 'rims, one broken clean in two. They were stacked straight on top of each other with a single '
                    . 'thin layer around the whole lot, which plaster is never going to survive. The pieces that '
                    . 'made it are nicely detailed, which makes it more annoying, we were 8 short on the day and '
                    . 'had to rearrange the entire table.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Not another dry fruit box',
                'name'   => 'Vikrant Chauhan',
                'review' => 'We needed 80 pieces for corporate Diwali gifting and did not want to send the usual dry '
                    . 'fruit box, so we went with the terrazzo tealight holders. The finish was uniform across all '
                    . '80 and the carton reached our Gurgaon office in a week with the invoice in order. Colleagues '
                    . 'actually liked them, which is rare for a corporate gift.',
            ],
            [
                'rate'   => 4,
                'title'  => 'Trays for my candle unit',
                'name'   => 'Vidya Kamat',
                'review' => 'Bought 12 multi cavity trays for my small candle unit, the silicone is thick and demoulds '
                    . 'clean, only the heart cavities are shallower than they looked in the listing.',
            ],
            [
                'rate'   => 5,
                'title'  => 'Guests asked for the source',
                'name'   => 'Swati Mohanty',
                'review' => 'Ordered 50 pieces for our griha pravesh, mostly lotus tealight holders with a few small '
                    . 'planters. Everything came in one well filled carton and nothing was damaged. The pieces look '
                    . 'handmade in a good way rather than machine turned, and two of my aunties took down the '
                    . 'details before they left.',
            ],
        ],
    ];
}
