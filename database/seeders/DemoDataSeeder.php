<?php

namespace Database\Seeders;

use App\Modules\Competitors\Models\Competitor;
use App\Modules\Finance\Models\BankTransaction;
use App\Modules\Finance\Models\GstTransaction;
use App\Modules\Finance\Models\Order;
use App\Modules\Finance\Models\Settlement;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductIntelligenceService;
use App\Modules\Reconciliation\Models\ReconciliationRun;
use App\Modules\Reconciliation\Services\ReconciliationEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    // 90-day window ending today
    private Carbon $start;
    private Carbon $end;
    private int $workspaceId = 1;
    private int $userId      = 2; // Demo Seller
    private ImportBatch $batch;

    // ─── Product catalogue ───────────────────────────────────────────────
    private array $products = [
        // Electronics
        [
            'asin' => 'B09ELEC0001', 'sku' => 'ELC-BTE-001', 'category' => 'Electronics',
            'brand' => 'SoundMax', 'price' => 1299.00, 'rating' => 4.3, 'review_count' => 2847,
            'title' => 'SoundMax Pro Wireless Earbuds with Active Noise Cancellation | 32Hr Battery | IPX5 Waterproof | Bluetooth 5.3 | Touch Controls | for Android & iOS',
            'bullet_1' => 'SUPERIOR SOUND QUALITY: Custom-tuned 10mm drivers deliver powerful bass and crystal-clear highs. Advanced Active Noise Cancellation blocks up to 25dB of ambient noise for an immersive listening experience.',
            'bullet_2' => 'EXTENDED BATTERY LIFE: Enjoy 8 hours of playtime per charge with the earbuds, plus an additional 24 hours with the compact charging case — totalling 32 hours of uninterrupted music.',
            'bullet_3' => 'IPX5 WATERPROOF RATING: Sweat-resistant and splash-proof design makes these earbuds perfect for workouts, running, and outdoor activities in all weather conditions.',
            'bullet_4' => 'BLUETOOTH 5.3 CONNECTIVITY: Faster pairing, stable 10-metre range, and lower latency for lag-free audio and video synchronisation across all Bluetooth-enabled devices.',
            'bullet_5' => 'ERGONOMIC FIT: Three sizes of silicone ear tips included (S/M/L). The lightweight 5g design ensures all-day comfort without ear fatigue, even during extended use.',
            'description' => '<p>Introducing the <strong>SoundMax Pro Wireless Earbuds</strong> — your perfect audio companion for work, workouts, and everything in between. Crafted for the discerning music lover who demands professional-grade audio in a compact, everyday package.</p><p>The dual microphone setup with AI-powered noise suppression ensures crystal-clear calls in busy offices, crowded commutes, or windy outdoor environments. Whether you\'re on an important business call or enjoying your favourite podcast, your voice comes through with stunning clarity.</p><p>Compatible with SoundMax app (Android & iOS) for personalised EQ settings, firmware updates, and find-my-earbuds feature. One-year manufacturer warranty included.</p>',
        ],
        [
            'asin' => 'B09ELEC0002', 'sku' => 'ELC-CHG-001', 'category' => 'Electronics',
            'brand' => 'PowerUp', 'price' => 899.00, 'rating' => 4.1, 'review_count' => 1234,
            'title' => 'PowerUp 20000mAh Power Bank | 22.5W Fast Charging | Dual USB-A + USB-C | LED Display | Compatible with iPhone, Samsung, OnePlus & All Smartphones',
            'bullet_1' => 'MASSIVE 20000mAh CAPACITY: Charge your smartphone up to 5 times, tablets twice, or earbuds over 20 times on a single charge. Never run out of power on long trips or workdays again.',
            'bullet_2' => '22.5W FAST CHARGING: Supports Qualcomm Quick Charge 3.0 and PD 20W. Charge your compatible device from 0 to 50% in just 30 minutes — significantly faster than standard chargers.',
            'bullet_3' => 'MULTIPLE OUTPUT PORTS: Dual USB-A ports plus one USB-C port allows simultaneous charging of three devices. Intelligent power distribution ensures each device gets optimal charging speed.',
            'bullet_4' => 'SMART LED DISPLAY: Real-time battery percentage display eliminates guesswork. Built-in safety features include overcharge, over-discharge, overcurrent, and temperature protection.',
            'bullet_5' => 'AIRLINE APPROVED: Compact 160g design with TSA-compliant 74Wh capacity. Comes with USB-C to USB-C cable and USB-A to USB-C cable. 18-month warranty with dedicated customer support.',
            'description' => '<p>The <strong>PowerUp 20000mAh Power Bank</strong> is engineered for modern, connected lifestyles. Built with premium Grade-A lithium cells, this power bank maintains consistent performance through hundreds of charge cycles.</p><p>The smart trickle-charging mode for low-current devices like earbuds and smartwatches prevents overcharging damage. The industrial-grade shock-resistant casing protects against drops and impacts during daily commutes.</p>',
        ],
        [
            'asin' => 'B09ELEC0003', 'sku' => 'ELC-LST-001', 'category' => 'Electronics',
            'brand' => 'ErgoDesk', 'price' => 1599.00, 'rating' => 4.5, 'review_count' => 892,
            'title' => 'ErgoDesk Aluminium Laptop Stand Adjustable | 6 Height Settings | Foldable & Portable | Heat Dissipation | Compatible with MacBook Pro/Air, Dell, HP, Lenovo 10-15.6 Inch',
            'bullet_1' => 'PREMIUM ALUMINIUM BUILD: Aircraft-grade 6061 aluminium construction provides exceptional stability while remaining lightweight at just 580g. Supports laptops up to 8kg without wobble or flex.',
            'bullet_2' => '6 ADJUSTABLE HEIGHT SETTINGS: Ranges from 52mm to 168mm elevation, bringing your laptop screen to eye level. Reduces neck strain and improves posture during long work-from-home sessions.',
            'bullet_3' => 'SUPERIOR HEAT DISSIPATION: Hollow ventilation design promotes 360-degree airflow beneath your laptop, preventing overheating and maintaining optimal performance during intensive tasks.',
            'bullet_4' => 'FOLDABLE & PORTABLE: Collapses to a flat 3cm profile in seconds. Lightweight design fits in any laptop bag. Cable management clips included to keep your workspace tidy.',
            'bullet_5' => 'UNIVERSAL COMPATIBILITY: Fits MacBook Air/Pro 13" to 16", Dell XPS, HP Spectre, Lenovo ThinkPad, and all laptops from 10" to 15.6". Non-slip silicone pads protect your device from scratches.',
            'description' => '<p>Transform your workspace with the <strong>ErgoDesk Aluminium Laptop Stand</strong>. Ergonomics research shows that working at eye level reduces neck and shoulder strain by up to 44%, improving both comfort and productivity.</p><p>The precision-engineered hinge mechanism provides smooth, one-hand adjustability and locks securely at each height setting. No tools required for setup or adjustment — simply unfold and start working in under 10 seconds.</p>',
        ],
        [
            'asin' => 'B09ELEC0004', 'sku' => 'ELC-PHC-001', 'category' => 'Electronics',
            'brand' => 'ArmorCase', 'price' => 399.00, 'rating' => 4.0, 'review_count' => 3421,
            'title' => 'ArmorCase Military Grade Protection Case for Samsung Galaxy S24 | Shockproof | Drop Tested 2 Metres | Wireless Charging Compatible | Slim Design',
            'bullet_1' => 'MILITARY-GRADE PROTECTION: MIL-STD-810G certified protection against drops, shocks, and impacts from up to 2 metres. Reinforced corner bumpers absorb impact before it reaches your device.',
            'bullet_2' => 'SLIM YET PROTECTIVE: Only 12mm thick — thinner than most protective cases. Precisely moulded to maintain your phone\'s original slim profile while providing maximum protection.',
            'bullet_3' => 'WIRELESS CHARGING COMPATIBLE: No need to remove the case for wireless charging. Works seamlessly with Qi-certified chargers, Samsung DeX, and Samsung Wireless PowerShare.',
            'bullet_4' => 'PRECISE CUTOUTS: Every port, button, and camera lens is precisely cut for perfect access. Tactile buttons provide satisfying click feedback even through the case.',
            'bullet_5' => 'LIFETIME WARRANTY: We stand behind our product with a lifetime manufacturer warranty. If your case ever fails to protect your device, we\'ll replace it free of charge.',
            'description' => '<p>The <strong>ArmorCase</strong> combines serious protection with everyday usability. The dual-layer design features a rigid polycarbonate inner shell surrounded by a flexible TPU outer layer — working together to dissipate impact energy before it reaches your Samsung Galaxy S24.</p>',
        ],
        [
            'asin' => 'B09ELEC0005', 'sku' => 'ELC-SCR-001', 'category' => 'Electronics',
            'brand' => 'ClearGuard', 'price' => 299.00, 'rating' => 3.9, 'review_count' => 5672,
            'title' => 'ClearGuard Tempered Glass Screen Protector for iPhone 15 | Pack of 2 | 9H Hardness | Anti-Fingerprint | Case Friendly | Ultra-Clear | Easy Install Frame',
            'bullet_1' => '9H TEMPERED GLASS: Made from premium Japanese Asahi glass, rated 9H on the Mohs hardness scale. Resists scratches from keys, coins, and everyday carry items that would damage standard glass.',
            'bullet_2' => 'CRYSTAL CLEAR CLARITY: 99.9% optical transparency maintains your iPhone\'s original display quality. High-definition anti-reflective coating reduces glare for comfortable outdoor viewing.',
            'bullet_3' => 'EASY INSTALLATION FRAME: Includes precision-fit installation frame that eliminates bubbles and misalignment — guaranteed perfect placement every time, even for first-time installers.',
            'bullet_4' => 'ANTI-FINGERPRINT COATING: Oleophobic coating repels fingerprints and smudges. Stays clean and clear with minimal wiping, maintaining pristine appearance throughout the day.',
            'bullet_5' => 'PACK OF 2: Two screen protectors included. Case-friendly design works with most iPhone 15 cases without lifting or peeling. Dust removal stickers and alcohol wipes included.',
            'description' => '<p>Protect your investment with <strong>ClearGuard Tempered Glass</strong> — engineered specifically for the iPhone 15\'s exact dimensions. The ultra-thin 0.33mm profile is virtually undetectable, maintaining full touch sensitivity and Face ID functionality.</p>',
        ],

        // Kitchen
        [
            'asin' => 'B09KITC0001', 'sku' => 'KIT-MUG-001', 'category' => 'Kitchen & Dining',
            'brand' => 'BrewCraft', 'price' => 449.00, 'rating' => 4.4, 'review_count' => 1876,
            'title' => 'BrewCraft Ceramic Coffee Mug 350ml | Microwave & Dishwasher Safe | Double-Walled Insulation | Gift Box Included | Perfect for Coffee, Tea & Hot Chocolate',
            'bullet_1' => 'PREMIUM FOOD-GRADE CERAMIC: Made from high-quality, lead-free and cadmium-free ceramic. Glazed interior ensures no flavour transfer between beverages and easy, thorough cleaning.',
            'bullet_2' => 'DOUBLE-WALLED INSULATION: Keeps your beverage hot for 2 hours and cold for 4 hours. The outer surface stays comfortable to hold even with piping hot liquids inside.',
            'bullet_3' => 'PERFECT 350ML CAPACITY: The ideal size for a standard mug of coffee, tea, or hot chocolate. Large enough for a generous serving without being too heavy or bulky.',
            'bullet_4' => 'MICROWAVE & DISHWASHER SAFE: Tested and certified safe for both microwave reheating and dishwasher cleaning at all standard temperature settings. Colours remain vibrant after hundreds of washes.',
            'bullet_5' => 'BEAUTIFUL GIFT BOX: Arrives in a premium kraft gift box, ready to give. Perfect birthday, anniversary, or housewarming gift for coffee and tea lovers. Personalised card option available.',
            'description' => '<p>Elevate your daily ritual with the <strong>BrewCraft Ceramic Coffee Mug</strong>. Each mug is individually inspected for quality before packaging. The ergonomic handle is designed for a comfortable, secure grip — wide enough for large hands, properly angled for natural wrist position.</p><p>Available in 8 colours to complement any kitchen aesthetic. Whether you prefer bold espresso, delicate white teas, or indulgent hot chocolate, this mug enhances every sipping experience.</p>',
        ],
        [
            'asin' => 'B09KITC0002', 'sku' => 'KIT-BTL-001', 'category' => 'Kitchen & Dining',
            'brand' => 'HydraFlow', 'price' => 799.00, 'rating' => 4.6, 'review_count' => 3241,
            'title' => 'HydraFlow Insulated Stainless Steel Water Bottle 750ml | Keeps Cold 24Hr Hot 12Hr | BPA Free | Leak-Proof Lid | Sports, Gym, Office & Outdoor Use',
            'bullet_1' => 'TRIPLE-LAYER VACUUM INSULATION: Food-grade 18/8 stainless steel with vacuum-sealed triple insulation. Clinically proven to maintain cold beverages for 24 hours and hot beverages for 12 hours.',
            'bullet_2' => '100% BPA-FREE CONSTRUCTION: Made entirely from BPA-free stainless steel and food-safe materials. No plastic parts contact your beverage — maintaining pure taste without chemical leaching.',
            'bullet_3' => 'GUARANTEED LEAK-PROOF: Patent-pending twist-lock lid design prevents leaks even when stored upside down in a bag. The silicone gasket maintains an airtight seal with every use.',
            'bullet_4' => 'WIDE-MOUTH DESIGN: 50mm wide opening accommodates ice cubes and is easy to clean by hand or with a standard bottle brush. The powder-coated exterior provides a non-slip grip.',
            'bullet_5' => 'LIFETIME GUARANTEE: We\'re so confident in our quality that we offer a lifetime guarantee against manufacturing defects. Register your bottle on our website for priority customer support.',
            'description' => '<p>The <strong>HydraFlow Insulated Water Bottle</strong> is built for people who refuse to compromise on hydration. Whether you\'re hitting the gym, hiking mountain trails, or sitting through back-to-back meetings, this bottle keeps your drink at the perfect temperature all day.</p>',
        ],
        [
            'asin' => 'B09KITC0003', 'sku' => 'KIT-CHB-001', 'category' => 'Kitchen & Dining',
            'brand' => 'ChefWood', 'price' => 649.00, 'rating' => 4.2, 'review_count' => 687,
            'title' => 'ChefWood Acacia Wood Chopping Board Large 40x30cm | Juice Grooves | Non-Slip Feet | Anti-Bacterial | Knife-Friendly Surface | Dishwasher Safe',
            'bullet_1' => 'PREMIUM ACACIA HARDWOOD: Responsibly sourced, kiln-dried acacia wood — harder and more durable than bamboo or pine. The tight grain prevents moisture absorption and bacterial growth.',
            'bullet_2' => 'JUICE GROOVES PREVENT MESS: Deep 8mm perimeter juice grooves channel meat and fruit juices away from your workspace, keeping your countertop clean during prep.',
            'bullet_3' => 'FOUR NON-SLIP FEET: Rubberised feet on all four corners ensure the board stays firmly in place during chopping. No more board sliding on your countertop mid-cut.',
            'bullet_4' => 'KNIFE-FRIENDLY SURFACE: Harder than plastic but softer than glass or stone — protects your knife edges from dulling while providing the ideal cutting resistance for professional results.',
            'bullet_5' => 'EASY MAINTENANCE: Hand wash with mild soap and warm water. Apply food-safe mineral oil every month to maintain the wood\'s natural beauty and extend its lifespan for years of reliable use.',
            'description' => '<p>The <strong>ChefWood Acacia Chopping Board</strong> brings professional kitchen quality to your home. Measuring a generous 40x30cm, it provides ample workspace for chopping large vegetables, carving roasts, or preparing multiple ingredients simultaneously.</p>',
        ],
        [
            'asin' => 'B09KITC0004', 'sku' => 'KIT-CWR-001', 'category' => 'Kitchen & Dining',
            'brand' => 'IronChef', 'price' => 2499.00, 'rating' => 4.5, 'review_count' => 412,
            'title' => 'IronChef 3-Piece Non-Stick Cookware Set | Kadai 24cm + Fry Pan 26cm + Sauce Pan 20cm | Induction & Gas Compatible | PFOA-Free Coating | Glass Lids',
            'bullet_1' => 'PFOA-FREE NON-STICK COATING: Professional-grade, PFOA-free ceramic non-stick coating requires minimal oil for healthy cooking. Food releases effortlessly and cleanup takes just seconds.',
            'bullet_2' => 'INDUCTION & GAS COMPATIBLE: The magnetic stainless steel base works on all cooking surfaces — induction, gas, electric, and ceramic hobs. Even heat distribution eliminates hot spots.',
            'bullet_3' => 'ERGONOMIC STAY-COOL HANDLES: Riveted stainless steel handles with heat-resistant silicone grip stay cool during stovetop cooking. Dual handles on the kadai provide secure, balanced lifting.',
            'bullet_4' => 'TEMPERED GLASS LIDS: Shatter-resistant tempered glass lids allow monitoring without lifting. Steam vents prevent pressure buildup. Lids are compatible across all three pieces.',
            'bullet_5' => '3-PIECE COMPLETE SET: Includes 24cm kadai with lid (ideal for curries and dal), 26cm fry pan (perfect for dosas and stir-fries), and 20cm sauce pan with lid for gravies and boiling.',
            'description' => '<p>Cook healthier meals with less oil using the <strong>IronChef 3-Piece Cookware Set</strong>. The superior non-stick properties mean you can cook with up to 80% less fat than traditional cookware. The hard-anodised exterior resists warping and maintains its appearance even with daily use at high temperatures.</p>',
        ],
        [
            'asin' => 'B09KITC0005', 'sku' => 'KIT-JUG-001', 'category' => 'Kitchen & Dining',
            'brand' => 'AquaPure', 'price' => 1199.00, 'rating' => 4.3, 'review_count' => 934,
            'title' => 'AquaPure 3.5L Water Jug with Infuser & Lid | BPA-Free Tritan Material | Detachable Fruit Infuser | Fridge Compatible | Family Size | Handle & Spout',
            'bullet_1' => 'PREMIUM TRITAN MATERIAL: Made from BPA-free, shatter-resistant Tritan — the same material used in medical-grade water containers. Crystal-clear transparency lets you see your infusions clearly.',
            'bullet_2' => 'BUILT-IN FRUIT INFUSER: Removable stainless steel mesh infuser basket holds fruits, herbs, and vegetables for natural flavour infusions. Makes cucumber water, mint lemonade, and fruit teas effortlessly.',
            'bullet_3' => 'FAMILY-SIZED 3.5L CAPACITY: Large enough to hydrate a family of four throughout the day. Fits standard refrigerator shelves and door compartments with the lid on.',
            'bullet_4' => 'EASY POUR DESIGN: Wide-mouth opening simplifies filling and cleaning. The angled spout and comfortable handle allow drip-free pouring even when the jug is full.',
            'bullet_5' => 'COMPLETE PACKAGE: Includes the 3.5L jug, removable infuser basket, locking lid, cleaning brush, and recipe booklet with 20 infusion ideas. Dishwasher safe (top rack).',
            'description' => '<p>Stay hydrated the delicious way with the <strong>AquaPure Infuser Water Jug</strong>. Infused water is proven to increase daily water intake by 20-30%, making it easier to meet your hydration goals. The wide temperature range (-20°C to +100°C) means you can use it for cold infusions or room-temperature preparations.</p>',
        ],

        // Sports & Fitness
        [
            'asin' => 'B09SPRT0001', 'sku' => 'SPT-YMT-001', 'category' => 'Sports & Fitness',
            'brand' => 'FlexZone', 'price' => 899.00, 'rating' => 4.4, 'review_count' => 2156,
            'title' => 'FlexZone 6mm TPE Yoga Mat | Anti-Slip | Extra Large 183x61cm | Alignment Lines | Carrying Strap | Eco-Friendly | for Yoga, Pilates, Meditation & Gym',
            'bullet_1' => 'SUPERIOR ANTI-SLIP TEXTURE: Double-sided textured surface provides exceptional grip on both sides — preventing mat movement on floors and stopping your hands from slipping during poses.',
            'bullet_2' => 'IDEAL 6MM THICKNESS: The perfect balance between cushioning and stability. Thick enough to protect your knees and joints during floor exercises, yet firm enough for standing balance poses.',
            'bullet_3' => 'ALIGNMENT LINES: Printed alignment lines help beginners and advanced practitioners maintain correct posture and symmetrical positioning for maximum benefit and injury prevention.',
            'bullet_4' => 'ECO-FRIENDLY TPE MATERIAL: Made from Thermoplastic Elastomer (TPE) — biodegradable, latex-free, and free from harmful chemicals. Safe for skin contact and the environment.',
            'bullet_5' => 'COMPLETE PACKAGE: Includes a durable carrying strap for easy transport. The open-cell structure allows the mat to air-dry quickly, preventing odour buildup after intense sessions.',
            'description' => '<p>The <strong>FlexZone TPE Yoga Mat</strong> is designed to support your practice from beginner to advanced level. The generous 183x61cm dimensions accommodate all body types and yoga styles — from sun salutations to restorative poses. The moisture-wicking surface keeps the mat dry during hot yoga sessions.</p>',
        ],
        [
            'asin' => 'B09SPRT0002', 'sku' => 'SPT-PRO-001', 'category' => 'Sports & Fitness',
            'brand' => 'MuscleMix', 'price' => 599.00, 'rating' => 4.2, 'review_count' => 1432,
            'title' => 'MuscleMix Protein Shaker Bottle 700ml | Blenderball Wire Whisk | Leak-Proof | BPA-Free | Measurement Markings | Flip-Top Lid | for Protein Shakes, Pre-Workout',
            'bullet_1' => 'BLENDERBALL WIRE WHISK: Surgical-grade stainless steel BlenderBall wire whisk breaks down lumps and clumps as you shake, creating smooth, lump-free protein shakes every time.',
            'bullet_2' => 'LEAK-PROOF GUARANTEE: The patented seal system and secure flip-top lid ensure absolutely no leaks, even when stored sideways in your gym bag. Tested to 100+ drop cycles.',
            'bullet_3' => 'LARGE 700ML CAPACITY: Accommodates two full scoops of protein powder plus water or milk. Generous size also works for pre-workout drinks, meal replacement shakes, and juice.',
            'bullet_4' => 'PRECISE MEASUREMENT MARKINGS: Embossed ml and oz markings on both sides allow accurate measurement without additional measuring cups. Scale visible from all angles.',
            'bullet_5' => 'DISHWASHER SAFE: All components — bottle, lid, and BlenderBall — are top-rack dishwasher safe for thorough cleaning. Odour-resistant material stays fresh even with daily use.',
            'description' => '<p>The <strong>MuscleMix Protein Shaker</strong> is the gym companion trusted by fitness enthusiasts across India. The rounded interior design eliminates corners where powder can get trapped — ensuring your entire serving gets mixed into every shake.</p>',
        ],
        [
            'asin' => 'B09SPRT0003', 'sku' => 'SPT-RBD-001', 'category' => 'Sports & Fitness',
            'brand' => 'ResistPro', 'price' => 749.00, 'rating' => 4.3, 'review_count' => 876,
            'title' => 'ResistPro 5-Loop Resistance Bands Set | Natural Latex | Light to Heavy Resistance | for Glutes, Legs, Arms | Yoga, Pilates, Physio | Carry Bag Included',
            'bullet_1' => 'SET OF 5 RESISTANCE LEVELS: Yellow (10lb), Green (20lb), Blue (30lb), Black (40lb), Red (50lb). Progressive resistance allows beginners and experienced athletes to train effectively.',
            'bullet_2' => 'PREMIUM NATURAL LATEX: Made from 100% natural latex — more durable than synthetic rubber. Resistant to snapping, stretching out, and odour. Each band lasts 1000+ uses.',
            'bullet_3' => 'FULL-BODY WORKOUT: Effective for glute activation, leg strengthening, upper body toning, and core work. Replace an entire set of weights for home workouts, travel, and physio exercises.',
            'bullet_4' => 'NON-SLIP DESIGN: The textured outer surface grips clothing and skin to prevent the bands from rolling or slipping during exercises — maintaining proper positioning throughout your set.',
            'bullet_5' => 'PORTABLE CARRY BAG: Includes a premium mesh drawstring bag for storage and travel. The complete set fits in any gym bag, making it ideal for home, office, hotel, or outdoor workouts.',
            'description' => '<p>The <strong>ResistPro Resistance Bands Set</strong> delivers a complete gym workout in the palm of your hand. Resistance training with bands has been shown to be equally effective as free weights for muscle building, while being significantly gentler on joints and connective tissue.</p>',
        ],
        [
            'asin' => 'B09SPRT0004', 'sku' => 'SPT-SKP-001', 'category' => 'Sports & Fitness',
            'brand' => 'JumpFit', 'price' => 499.00, 'rating' => 4.1, 'review_count' => 543,
            'title' => 'JumpFit Speed Jump Rope | Adjustable Steel Cable | Ball Bearing Handles | Counter Display | for Fitness, Boxing, Crossfit & Weight Loss | Adults & Kids',
            'bullet_1' => 'PRECISION BALL BEARINGS: 360-degree ball-bearing mechanism in both handles provides ultra-smooth, tangle-free rotation. Eliminates friction for faster spinning speed and consistent rhythm.',
            'bullet_2' => 'ADJUSTABLE STEEL CABLE: 3-metre steel cable with protective coating. Adjustable to any height from 4ft to 6.5ft using the simple adjustment screw — no cutting required.',
            'bullet_3' => 'BUILT-IN JUMP COUNTER: Digital LCD counter automatically tracks your jumps per session. Monitor your progress and set daily goals. Counter resets between sessions.',
            'bullet_4' => 'ERGONOMIC FOAM HANDLES: Contoured foam handles provide a comfortable, non-slip grip even with sweaty palms during intense cardio sessions. Comfortable for 30+ minute jump rope workouts.',
            'bullet_5' => 'BURNS 1000 CALORIES/HR: Jump rope is one of the most efficient cardiovascular exercises. 10 minutes of skipping burns the equivalent of 8-minute mile running. Ideal for weight loss and fitness.',
            'description' => '<p>The <strong>JumpFit Speed Jump Rope</strong> is designed for serious fitness enthusiasts who want an effective, portable cardio workout. Used by professional boxers, MMA fighters, and CrossFit athletes worldwide, jump rope training improves coordination, footwork, and cardiovascular fitness simultaneously.</p>',
        ],

        // Personal Care
        [
            'asin' => 'B09PCAR0001', 'sku' => 'PCA-FWS-001', 'category' => 'Personal Care',
            'brand' => 'PureGlow', 'price' => 349.00, 'rating' => 4.2, 'review_count' => 4321,
            'title' => 'PureGlow Vitamin C Face Wash 150ml | Brightening & Tan Removal | Sulfate-Free | For Oily & Combination Skin | Dermatologically Tested | Men & Women',
            'bullet_1' => '10% VITAMIN C COMPLEX: High-concentration Vitamin C formulation brightens skin tone, fades dark spots, and reduces hyperpigmentation. Visible results within 2 weeks of regular use.',
            'bullet_2' => 'SULFATE-FREE FORMULA: Free from SLS, SLES, and harsh sulphates that strip natural oils. The mild, pH-balanced formula cleans effectively without disrupting your skin\'s protective barrier.',
            'bullet_3' => 'TAN REMOVAL & BRIGHTENING: Niacinamide and kojic acid work synergistically to reduce sun tan, even skin tone, and restore natural radiance to dull, tired-looking skin.',
            'bullet_4' => 'DERMATOLOGICALLY TESTED: Tested and approved by certified dermatologists for all skin types including sensitive skin. Free from parabens, artificial fragrances, and harsh chemicals.',
            'bullet_5' => 'SUITABLE FOR MEN & WOMEN: Effective formulation for all genders dealing with oily skin, blackheads, uneven tone, and urban pollution damage. Use morning and evening for best results.',
            'description' => '<p>The <strong>PureGlow Vitamin C Face Wash</strong> combines the antioxidant power of Vitamin C with the brightening benefits of Niacinamide to transform dull, uneven skin into a radiant, luminous complexion.</p>',
        ],
        [
            'asin' => 'B09PCAR0002', 'sku' => 'PCA-HDR-001', 'category' => 'Personal Care',
            'brand' => 'BreezeStyle', 'price' => 1899.00, 'rating' => 4.3, 'review_count' => 2134,
            'title' => 'BreezeStyle Professional Hair Dryer 2000W | Ionic Technology | 3 Heat + 2 Speed Settings | Cool Shot Button | Concentrator Nozzle | Frizz-Free Salon Quality',
            'bullet_1' => 'POWERFUL 2000W MOTOR: Professional-grade 2000-watt motor dries hair 40% faster than standard dryers. Reduces heat damage from prolonged drying time and delivers salon-quality results at home.',
            'bullet_2' => 'IONIC TECHNOLOGY: Generates negative ions that break down water molecules for faster drying and seal the hair cuticle for exceptional shine. Reduces frizz by up to 75% compared to standard dryers.',
            'bullet_3' => '3 TEMPERATURE SETTINGS: Gentle warm setting for delicate, colour-treated hair. Medium setting for normal drying. Hot setting for thick, coarse hair. The cool shot button locks your style in place.',
            'bullet_4' => 'PROFESSIONAL CONCENTRATOR: The precision concentrator nozzle focuses airflow exactly where needed for precise styling. Removable for easy cleaning and compact storage.',
            'bullet_5' => 'SAFETY FEATURES: Built-in overheating protection automatically shuts down if the motor exceeds safe temperature. 1.8-metre tangle-free cord provides freedom of movement while styling.',
            'description' => '<p>The <strong>BreezeStyle Professional Hair Dryer</strong> brings the salon experience home with its advanced ionic technology and powerful 2000-watt motor. Whether you have straight, curly, wavy, or coarse hair, this versatile dryer delivers the precise heat and airflow needed for your ideal style.</p>',
        ],
        [
            'asin' => 'B09PCAR0003', 'sku' => 'PCA-BTB-001', 'category' => 'Personal Care',
            'brand' => 'EcoSmile', 'price' => 249.00, 'rating' => 4.4, 'review_count' => 1876,
            'title' => 'EcoSmile Bamboo Toothbrush Pack of 4 | BPA-Free Charcoal Bristles | Biodegradable Handle | Medium Softness | Eco-Friendly | Natural Oral Care',
            'bullet_1' => 'SUSTAINABLE BAMBOO HANDLE: Made from organically grown Moso bamboo — the world\'s fastest-growing plant. Each handle is fully compostable at end of life, unlike plastic toothbrushes.',
            'bullet_2' => 'CHARCOAL-INFUSED BRISTLES: BPA-free nylon bristles infused with activated charcoal naturally whiten teeth, freshen breath, and provide gentle antibacterial protection during brushing.',
            'bullet_3' => 'MEDIUM SOFTNESS: Precisely engineered medium-soft bristle firmness removes plaque effectively while being gentle enough for sensitive gums. Dentist-approved for daily use.',
            'bullet_4' => 'ERGONOMIC GRIP DESIGN: Each handle features a comfortable, naturally textured grip that prevents slipping even with wet hands. The curved neck positions bristles at the optimal 45-degree angle.',
            'bullet_5' => 'PACK OF 4: One toothbrush per family member, or a 4-month supply per person. Each brush individually wrapped in recycled paper packaging. Replace every 2-3 months for optimal hygiene.',
            'description' => '<p>Make the switch to sustainable oral care with <strong>EcoSmile Bamboo Toothbrushes</strong>. Over 4 billion plastic toothbrushes are discarded annually — most ending up in landfills or oceans. By choosing bamboo, you\'re making a meaningful choice for the environment without compromising on cleaning performance.</p>',
        ],
        [
            'asin' => 'B09PCAR0004', 'sku' => 'PCA-SUN-001', 'category' => 'Personal Care',
            'brand' => 'SolShield', 'price' => 599.00, 'rating' => 4.1, 'review_count' => 3210,
            'title' => 'SolShield SPF 50+ Sunscreen Lotion 100ml | PA++++ | UVA & UVB Protection | Non-Greasy | Water-Resistant | For Indian Skin | No White Cast',
            'bullet_1' => 'SPF 50+ & PA++++ RATING: Highest level of both UVB (SPF 50+) and UVA (PA++++) protection. Shields against sunburn, tanning, premature ageing, and long-term UV damage from Indian sun exposure.',
            'bullet_2' => 'ZERO WHITE CAST: Specifically formulated for Indian and South Asian skin tones. Transparent micro-dispersed formula absorbs instantly without leaving the white, chalky residue common in other sunscreens.',
            'bullet_3' => 'NON-GREASY MATTE FINISH: Lightweight gel-cream texture controls excess sebum. Leaves skin with a natural matte finish — comfortable to wear alone or under makeup throughout the day.',
            'bullet_4' => 'WATER & SWEAT RESISTANT: Maintains SPF 50+ protection for up to 80 minutes of water exposure. Ideal for swimming, outdoor sports, and commuting in India\'s humid conditions.',
            'bullet_5' => 'SKIN-BENEFITING FORMULA: Enriched with Hyaluronic Acid for hydration, Niacinamide for brightening, and Vitamin E for antioxidant protection. Dermatologically tested, non-comedogenic.',
            'description' => '<p><strong>SolShield SPF 50+ Sunscreen</strong> is clinically proven to protect Indian skin from the intense UV radiation typical of the Indian subcontinent. UV radiation in India is significantly stronger than in most Western countries due to geographic proximity to the equator — making daily sun protection essential year-round, not just in summer.</p>',
        ],

        // Home
        [
            'asin' => 'B09HOME0001', 'sku' => 'HOM-LED-001', 'category' => 'Home Improvement',
            'brand' => 'BrightLux', 'price' => 299.00, 'rating' => 4.3, 'review_count' => 8765,
            'title' => 'BrightLux 9W LED Bulb Pack of 10 | B22 Base | 850 Lumens | Warm White 3000K | Energy Saving | 25,000 Hour Life | ISI Marked | Eco-Friendly',
            'bullet_1' => 'ENERGY SAVING: 9 watts delivers the same brightness as a 60W incandescent bulb — saving 85% on electricity costs. With 10 bulbs and Indian electricity rates, saves ₹3,000+ annually.',
            'bullet_2' => 'LONG 25,000 HOUR LIFESPAN: Rated for 25,000 hours of operation — equivalent to over 22 years at 3 hours per day. Eliminates the cost and inconvenience of frequent bulb replacements.',
            'bullet_3' => '850 LUMENS BRIGHT OUTPUT: 850 lumens provides bright, even illumination suitable for living rooms, bedrooms, kitchens, and offices. Consistent brightness throughout the bulb\'s lifespan.',
            'bullet_4' => 'ISI MARKED & BEE 5-STAR: Bureau of Indian Standards ISI certified and Bureau of Energy Efficiency 5-star rated. Meets all Indian safety and efficiency standards for residential use.',
            'bullet_5' => 'INSTANT FULL BRIGHTNESS: Unlike CFL bulbs, LED technology provides full brightness immediately at switch-on with no warm-up period. Works in all enclosed and open fittings.',
            'description' => '<p>Upgrade your entire home to LED with the <strong>BrightLux 9W Bulb Pack of 10</strong>. Switching 10 standard 60W bulbs to these LED alternatives reduces your monthly electricity bill by an average of ₹250-400, making this purchase self-financing within the first year.</p>',
        ],
        [
            'asin' => 'B09HOME0002', 'sku' => 'HOM-PLT-001', 'category' => 'Home & Garden',
            'brand' => 'GreenSpace', 'price' => 549.00, 'rating' => 4.5, 'review_count' => 654,
            'title' => 'GreenSpace Self-Watering Planter Set of 3 | Ceramic Coating | Indoor Plant Pots | 12cm/16cm/20cm | Drainage Holes | for Succulents, Herbs & Indoor Plants',
            'bullet_1' => 'SELF-WATERING DESIGN: Built-in water reservoir at the base maintains consistent soil moisture through capillary action. Reduces watering frequency by up to 60% — ideal for busy plant parents.',
            'bullet_2' => 'SET OF 3 SIZES: Includes small (12cm), medium (16cm), and large (20cm) planters. Perfect for creating a layered display of succulents, herbs, peace lilies, or trailing plants.',
            'bullet_3' => 'CERAMIC-COATED INTERIOR: Food-safe ceramic coating prevents soil contact with the polypropylene body. Completely non-toxic, won\'t affect soil pH, and prevents root rot from overwatering.',
            'bullet_4' => 'DRAINAGE SYSTEM: Each pot features drainage holes and a separate drip tray. Excess water drains to the reservoir rather than overflowing — protecting your furniture and floors.',
            'bullet_5' => 'MODERN MINIMALIST DESIGN: Clean lines and matte white finish complement any interior style from Scandinavian to bohemian. The UV-resistant coating maintains colour even on bright windowsills.',
            'description' => '<p>The <strong>GreenSpace Self-Watering Planter Set</strong> makes indoor gardening virtually foolproof. The intelligent water reservoir system mimics natural rainfall patterns, delivering moisture to roots from below — just as plants prefer it. Root-to-water contact is eliminated, preventing the overwatering issues that kill most houseplants.</p>',
        ],
        [
            'asin' => 'B09HOME0003', 'sku' => 'HOM-DIF-001', 'category' => 'Home & Garden',
            'brand' => 'AromaCraft', 'price' => 1299.00, 'rating' => 4.4, 'review_count' => 1243,
            'title' => 'AromaCraft Ultrasonic Essential Oil Diffuser 500ml | 7-Colour LED | Auto Shut-Off | Whisper Quiet | Humidifier | 4 Timer Settings | for Bedroom & Office',
            'bullet_1' => 'LARGE 500ML CAPACITY: Provides up to 8 hours of continuous misting or 16 hours of intermittent misting on a single fill. Perfect for bedrooms, living rooms, and office spaces.',
            'bullet_2' => 'WHISPER-QUIET OPERATION: Ultrasonic technology operates at just 36 decibels — quieter than a whisper. Won\'t disturb sleep, meditation, work concentration, or yoga practice.',
            'bullet_3' => '7-COLOUR LED AMBIANCE: Choose from 7 soothing colours, cycle through all colours gradually, or turn the light off entirely. The warm diffused glow transforms any room into a relaxation sanctuary.',
            'bullet_4' => 'AUTO SAFETY SHUT-OFF: Automatically powers off when water level drops below the minimum threshold — preventing dry running and protecting the ultrasonic plate from damage.',
            'bullet_5' => '4 TIMER SETTINGS: Programme to run for 1, 2, 4, or 8 hours, or run continuously. The intermittent mode (30 sec on, 30 sec off) conserves essential oils while maintaining fragrance.',
            'description' => '<p>Transform your home into a personal wellness sanctuary with the <strong>AromaCraft Ultrasonic Diffuser</strong>. Ultrasonic technology breaks essential oils into millions of microscopic particles without heat, preserving their therapeutic properties and dispersing them evenly throughout the room.</p>',
        ],
        [
            'asin' => 'B09HOME0004', 'sku' => 'HOM-BLK-001', 'category' => 'Home & Garden',
            'brand' => 'CozyNest', 'price' => 1499.00, 'rating' => 4.6, 'review_count' => 987,
            'title' => 'CozyNest Knitted Throw Blanket 130x150cm | Super Soft Chunky Knit | 100% Organic Cotton | All-Season | Sofa, Bed & Gift | Oeko-Tex Certified | 8 Colours',
            'bullet_1' => '100% ORGANIC COTTON: Made from Oeko-Tex Standard 100 certified organic cotton — free from harmful chemicals and safe for even the most sensitive skin, including babies and allergy sufferers.',
            'bullet_2' => 'CHUNKY KNIT TEXTURE: The thick, open-weave chunky knit design provides warmth without weight. Light enough for year-round use — cosy in winter as a blanket, cool enough for summer air-conditioned rooms.',
            'bullet_3' => 'GENEROUS 130x150cm SIZE: Large enough to comfortably wrap around two adults on a sofa or use as a bed throw. The substantial size creates an elegant drape on any furniture.',
            'bullet_4' => 'PREMIUM GIFT PACKAGING: Arrives neatly folded in a premium fabric bag with cotton ribbon. Ready to gift without additional wrapping. Perfect for housewarmings, weddings, and birthdays.',
            'bullet_5' => 'MACHINE WASHABLE: Despite its artisanal appearance, it\'s fully machine washable on a gentle cycle. The double-twisted cotton yarn maintains its shape and softness through repeated washing.',
            'description' => '<p>The <strong>CozyNest Knitted Throw Blanket</strong> adds instant warmth and style to any living space. Handcrafted by skilled artisans using traditional knitting techniques with modern cotton yarn, each blanket takes approximately 8 hours to produce.</p>',
        ],

        // Stationery
        [
            'asin' => 'B09STAT0001', 'sku' => 'STA-NTB-001', 'category' => 'Stationery',
            'brand' => 'WriteWell', 'price' => 349.00, 'rating' => 4.5, 'review_count' => 3421,
            'title' => 'WriteWell A5 Hardcover Dot Grid Notebook | 240 Pages | 100gsm Fountain Pen Friendly Paper | Lay-Flat Binding | Bookmark Ribbon | Index Pages | Black',
            'bullet_1' => '100GSM FOUNTAIN PEN FRIENDLY: Premium acid-free paper rated for fountain pens, gel pens, and markers. Virtually no bleed-through or ghosting even with wet inks. Write with confidence on both sides.',
            'bullet_2' => 'DOT GRID FORMAT: 5mm dot grid spacing provides structure without visual clutter. Perfect for bullet journaling, hand lettering, sketching, diagrams, and mixed-use notebooks.',
            'bullet_3' => 'LAY-FLAT BINDING: Swiss binding technique allows the notebook to lie completely flat at any page without a hand holding it open. Eliminates the frustrating curve at the spine during writing.',
            'bullet_4' => '240 THICK PAGES: 120 sheets (240 pages) of premium paper provide months of writing space. Numbered pages and a table of contents index at the front help organise your thoughts.',
            'bullet_5' => 'PREMIUM HARDCOVER: Vegan leather hardcover resists scuffs, water, and daily wear. Elastic closure band keeps pages secure. Two ribbon bookmarks allow easy reference to multiple sections.',
            'description' => '<p>The <strong>WriteWell A5 Dot Grid Notebook</strong> is designed for people who take their writing seriously. Whether you\'re a bullet journaler, student, professional, or creative, the combination of premium paper and thoughtful design features creates a writing experience that elevates every session.</p>',
        ],
        [
            'asin' => 'B09STAT0002', 'sku' => 'STA-PNS-001', 'category' => 'Stationery',
            'brand' => 'InkFlow', 'price' => 599.00, 'rating' => 4.3, 'review_count' => 1234,
            'title' => 'InkFlow Premium Gel Pen Set of 12 | 0.5mm Ultra-Fine Tip | Smooth Writing | Vibrant Colours | Retractable | for School, Office, Journaling & Art',
            'bullet_1' => 'ULTRA-FINE 0.5MM TIP: Precision 0.5mm tip delivers crisp, clean lines ideal for detailed writing, note-taking, and intricate artwork. Lines are consistent from first to last stroke.',
            'bullet_2' => '12 VIBRANT COLOURS: Set includes 12 carefully selected colours spanning black, blue, red, green, purple, brown, orange, pink, light blue, light green, gold, and silver.',
            'bullet_3' => 'SMOOTH QUICK-DRY INK: Premium gel ink formula flows smoothly without skipping or blobbing. Dries in under 3 seconds to prevent smudging — particularly important for left-handed writers.',
            'bullet_4' => 'RETRACTABLE MECHANISM: Sturdy click-retractable mechanism protects the tip when not in use. No caps to lose. The balanced barrel design reduces hand fatigue during extended writing sessions.',
            'bullet_5' => 'MULTIPURPOSE USE: Perfect for school notes, office documents, bullet journaling, hand lettering, adult colouring books, and greeting cards. Acid-free ink is archival quality.',
            'description' => '<p>The <strong>InkFlow Premium Gel Pen Set</strong> delivers a writing experience that makes everyday tasks feel special. Gel ink provides the smoothness of a rollerball with the boldness of a ballpoint — the best of both worlds for students, professionals, and creative enthusiasts.</p>',
        ],

        // Miscellaneous high-performers
        [
            'asin' => 'B09MISC0001', 'sku' => 'MIS-BAG-001', 'category' => 'Bags & Luggage',
            'brand' => 'EcoCarry', 'price' => 699.00, 'rating' => 4.4, 'review_count' => 2341,
            'title' => 'EcoCarry Canvas Tote Bag Large | 100% Organic Cotton | 15L Capacity | Laptop Compartment | Reusable Grocery & Beach Bag | Washable | Natural & Sustainable',
            'bullet_1' => 'LARGE 15L CAPACITY: Spacious main compartment holds a 15-inch laptop, A4 folders, books, groceries, gym clothes, or beach essentials. The wide opening allows easy access to all contents.',
            'bullet_2' => 'LAPTOP SLEEVE: Padded internal sleeve securely fits laptops up to 15 inches. Velcro closure keeps your device protected and separate from other items without adding bulk.',
            'bullet_3' => 'ORGANIC COTTON CANVAS: Made from certified organic cotton — no pesticides, no bleach, no synthetic dyes. Tightly woven 12oz canvas handles loads up to 15kg without deforming.',
            'bullet_4' => 'MACHINE WASHABLE: The entire bag is machine washable at 30°C for regular cleaning. Air dry to maintain shape. The natural cotton gets softer with every wash.',
            'bullet_5' => 'REPLACES 500 PLASTIC BAGS: With proper care, this tote lasts 5+ years, replacing 500+ single-use plastic bags. The equivalent CO2 saving is approximately 50kg over the bag\'s lifetime.',
            'description' => '<p>The <strong>EcoCarry Canvas Tote</strong> is the sustainable bag that doesn\'t compromise on style or function. Designed for the conscious consumer who wants to reduce plastic waste without carrying an ugly jute bag, this tote is equally at home at the farmer\'s market, office, gym, or beach.</p>',
        ],
        [
            'asin' => 'B09MISC0002', 'sku' => 'MIS-PIL-001', 'category' => 'Bedding',
            'brand' => 'DreamRest', 'price' => 1799.00, 'rating' => 4.5, 'review_count' => 1654,
            'title' => 'DreamRest Memory Foam Cervical Pillow | Ergonomic Neck Support | Orthopedic Design | Cooling Gel Layer | CertiPUR-US Foam | for Neck & Shoulder Pain',
            'bullet_1' => 'ORTHOPEDIC CERVICAL DESIGN: Contoured shape supports the natural curve of your neck and spine. Recommended by physiotherapists for people with neck pain, stiff shoulders, and poor sleep posture.',
            'bullet_2' => 'COOLING GEL LAYER: 3cm gel-infused memory foam layer disperses body heat 5x faster than standard memory foam. No more waking up with a hot, sweaty neck in warm Indian nights.',
            'bullet_3' => 'CERTIPUR-US CERTIFIED FOAM: Foam certified free from harmful chemicals including formaldehyde, heavy metals, and ozone-depleting substances. Safe for people with allergies and chemical sensitivities.',
            'bullet_4' => 'DUAL-HEIGHT DESIGN: Higher lobe (12cm) for side sleepers, lower lobe (10cm) for back sleepers. Both lobes provide correct spinal alignment for their respective sleeping positions.',
            'bullet_5' => 'PREMIUM REMOVABLE COVER: Ultra-soft bamboo-cotton blend pillowcase is removable and machine washable. Naturally hypoallergenic and antimicrobial — resists dust mites and bacteria.',
            'description' => '<p>Wake up without neck pain with the <strong>DreamRest Memory Foam Cervical Pillow</strong>. Poor sleep posture is the leading cause of morning neck stiffness and headaches. This orthopedic pillow maintains perfect spinal alignment throughout the night, whether you sleep on your back or your side.</p>',
        ],
    ];

    public function run(): void
    {
        $this->start = now()->subDays(90);
        $this->end   = now();

        $this->command->info('🚀 Creating demo data...');

        $this->batch = ImportBatch::create([
            'workspace_id' => $this->workspaceId,
            'user_id'      => $this->userId,
            'type'         => 'products',
            'original_filename' => 'demo_products.csv',
            'status'       => 'completed',
        ]);

        $createdProducts = $this->seedProducts();
        $this->command->info("✅ Created " . count($createdProducts) . " products");

        $this->seedOrders($createdProducts);
        $this->command->info("✅ Seeded orders");

        $this->seedSettlements($createdProducts);
        $this->command->info("✅ Seeded settlements");

        $this->seedBankTransactions();
        $this->command->info("✅ Seeded bank transactions");

        $this->seedGstTransactions($createdProducts);
        $this->command->info("✅ Seeded GST transactions");

        $this->seedCompetitors($createdProducts);
        $this->command->info("✅ Seeded competitors");

        $this->analyzeProducts($createdProducts);
        $this->command->info("✅ Analyzed products (listing scores calculated)");

        $this->runReconciliation();
        $this->command->info("✅ Reconciliation run completed");

        $this->command->info('');
        $this->command->info('🎉 Demo data ready! Open http://localhost:7801 and explore all three panels.');
    }

    // ─── Products ────────────────────────────────────────────────────────

    private function seedProducts(): array
    {
        $created = [];
        foreach ($this->products as $p) {
            $product = Product::updateOrCreate(
                ['workspace_id' => $this->workspaceId, 'asin' => $p['asin']],
                [
                    'import_batch_id' => $this->batch->id,
                    'sku'             => $p['sku'],
                    'title'           => $p['title'],
                    'brand'           => $p['brand'],
                    'category'        => $p['category'],
                    'bullet_1'        => $p['bullet_1'],
                    'bullet_2'        => $p['bullet_2'],
                    'bullet_3'        => $p['bullet_3'],
                    'bullet_4'        => $p['bullet_4'],
                    'bullet_5'        => $p['bullet_5'],
                    'description'     => $p['description'],
                    'price'           => $p['price'],
                    'currency'        => 'INR',
                    'rating'          => $p['rating'],
                    'review_count'    => $p['review_count'],
                    'source_type'     => 'csv',
                ]
            );
            $created[] = $product;
        }
        return $created;
    }

    // ─── Orders ──────────────────────────────────────────────────────────

    private function seedOrders(array $products): void
    {
        $importBatch = ImportBatch::create([
            'workspace_id' => $this->workspaceId,
            'user_id'      => $this->userId,
            'type'         => 'orders',
            'original_filename' => 'demo_orders.csv',
            'status'       => 'completed',
        ]);

        $orders = [];
        $orderNum = 100001;

        foreach ($products as $product) {
            // Each product gets 15-40 orders over 90 days
            $count = rand(15, 40);

            for ($i = 0; $i < $count; $i++) {
                $date     = Carbon::createFromTimestamp(
                    rand($this->start->timestamp, $this->end->timestamp)
                );
                $qty      = rand(1, 3);
                $price    = (float) $product->price;
                $tax      = round($price * $qty * 0.18, 2);
                $isCancelled = rand(1, 10) === 1; // 10% cancellation rate
                $isFBA    = rand(1, 3) !== 1;    // 66% FBA

                $orders[] = [
                    'workspace_id'            => $this->workspaceId,
                    'import_batch_id'         => $importBatch->id,
                    'amazon_order_id'         => '403-' . str_pad($orderNum++, 7, '0', STR_PAD_LEFT) . '-' . rand(1000000, 9999999),
                    'purchase_date'           => $date->toDateTimeString(),
                    'last_updated_date'       => $date->addDays(1)->toDateTimeString(),
                    'order_status'            => $isCancelled ? 'Cancelled' : 'Shipped',
                    'fulfillment_channel'     => $isFBA ? 'AFN' : 'MFN',
                    'sales_channel'           => 'Amazon.in',
                    'ship_service_level'      => $isFBA ? 'Expedited' : 'Standard',
                    'sku'                     => $product->sku,
                    'asin'                    => $product->asin,
                    'product_name'            => mb_substr($product->title, 0, 100),
                    'item_status'             => 'Shipped',
                    'quantity'                => $qty,
                    'currency'                => 'INR',
                    'item_price'              => $price * $qty,
                    'item_tax'                => $tax,
                    'shipping_price'          => $isFBA ? 0 : 49,
                    'shipping_tax'            => $isFBA ? 0 : 8.82,
                    'gift_wrap_price'         => 0,
                    'gift_wrap_tax'           => 0,
                    'item_promotion_discount' => rand(0, 5) === 0 ? round($price * 0.05, 2) : 0,
                    'ship_promotion_discount' => 0,
                    'ship_city'               => $this->randomCity(),
                    'ship_state'              => $this->randomState(),
                    'ship_postal_code'        => (string) rand(100000, 999999),
                    'ship_country'            => 'IN',
                    'is_business_order'       => false,
                    'created_at'              => $date->toDateTimeString(),
                ];
            }
        }

        // Insert in chunks
        foreach (array_chunk($orders, 200) as $chunk) {
            DB::table('orders')->insert($chunk);
        }
    }

    // ─── Settlements ──────────────────────────────────────────────────────

    private function seedSettlements(array $products): void
    {
        $importBatch = ImportBatch::create([
            'workspace_id' => $this->workspaceId,
            'user_id'      => $this->userId,
            'type'         => 'settlements',
            'original_filename' => 'demo_settlements.csv',
            'status'       => 'completed',
        ]);

        // 6 bi-weekly settlement cycles
        $rows = [];
        for ($cycle = 0; $cycle < 6; $cycle++) {
            $cycleStart   = $this->start->copy()->addDays($cycle * 14);
            $cycleEnd     = $cycleStart->copy()->addDays(13);
            $depositDate  = $cycleEnd->copy()->addDays(3);
            $settlementId = 'SETT' . str_pad(rand(1000000, 9999999), 10, '0', STR_PAD_LEFT);

            // Total payout for this cycle
            $totalPayout = rand(85000, 250000);

            // Order settlements (positive amounts)
            foreach ($products as $product) {
                $amount = (float) $product->price * rand(2, 8);
                $rows[] = [
                    'workspace_id'          => $this->workspaceId,
                    'import_batch_id'       => $importBatch->id,
                    'settlement_id'         => $settlementId,
                    'settlement_start_date' => $cycleStart->toDateString(),
                    'settlement_end_date'   => $cycleEnd->toDateString(),
                    'deposit_date'          => $depositDate->toDateString(),
                    'deposited_amount'      => $totalPayout,
                    'currency'              => 'INR',
                    'transaction_type'      => 'Order',
                    'order_id'              => '403-' . rand(1000000, 9999999) . '-' . rand(1000000, 9999999),
                    'amount_type'           => 'ItemPrice',
                    'amount_description'    => 'Principal',
                    'amount'                => $amount,
                    'posted_date'           => $cycleEnd->toDateString(),
                    'sku'                   => $product->sku,
                    'quantity_purchased'    => rand(1, 3),
                    'created_at'            => $cycleEnd->toDateTimeString(),
                ];
            }

            // FBA fees (negative amounts)
            $rows[] = [
                'workspace_id'          => $this->workspaceId,
                'import_batch_id'       => $importBatch->id,
                'settlement_id'         => $settlementId,
                'settlement_start_date' => $cycleStart->toDateString(),
                'settlement_end_date'   => $cycleEnd->toDateString(),
                'deposit_date'          => $depositDate->toDateString(),
                'deposited_amount'      => $totalPayout,
                'currency'              => 'INR',
                'transaction_type'      => 'FBAPerUnitFulfillmentFee',
                'order_id'              => null,
                'amount_type'           => 'ItemFees',
                'amount_description'    => 'FBA Per Unit Fulfillment Fee',
                'amount'                => -round($totalPayout * 0.12, 2),
                'posted_date'           => $cycleEnd->toDateString(),
                'sku'                   => null,
                'quantity_purchased'    => null,
                'created_at'            => $cycleEnd->toDateTimeString(),
            ];

            // Referral fees
            $rows[] = [
                'workspace_id'          => $this->workspaceId,
                'import_batch_id'       => $importBatch->id,
                'settlement_id'         => $settlementId,
                'settlement_start_date' => $cycleStart->toDateString(),
                'settlement_end_date'   => $cycleEnd->toDateString(),
                'deposit_date'          => $depositDate->toDateString(),
                'deposited_amount'      => $totalPayout,
                'currency'              => 'INR',
                'transaction_type'      => 'Commission',
                'order_id'              => null,
                'amount_type'           => 'ItemFees',
                'amount_description'    => 'Referral Fee',
                'amount'                => -round($totalPayout * 0.08, 2),
                'posted_date'           => $cycleEnd->toDateString(),
                'sku'                   => null,
                'quantity_purchased'    => null,
                'created_at'            => $cycleEnd->toDateTimeString(),
            ];
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('settlements')->insert($chunk);
        }
    }

    // ─── Bank Transactions ────────────────────────────────────────────────

    private function seedBankTransactions(): void
    {
        $importBatch = ImportBatch::create([
            'workspace_id' => $this->workspaceId,
            'user_id'      => $this->userId,
            'type'         => 'bank_statement',
            'original_filename' => 'demo_bank_statement.csv',
            'status'       => 'completed',
        ]);

        $rows    = [];
        $balance = 50000.00;

        // 6 Amazon credits (bi-weekly)
        for ($i = 0; $i < 6; $i++) {
            $date   = $this->start->copy()->addDays(($i * 14) + 16);
            $credit = rand(75000, 220000) + rand(0, 99) / 100;
            $balance += $credit;

            $settlementRef = 'SETT' . rand(1000000000, 9999999999);
            $rows[] = [
                'workspace_id'    => $this->workspaceId,
                'import_batch_id' => $importBatch->id,
                'transaction_date'=> $date->toDateString(),
                'value_date'      => $date->copy()->addDay()->toDateString(),
                'description'     => "AMAZON SELLER SERVICES INDIA PVT LTD NEFT UTR" . rand(100000000000000000, 999999999999999999),
                'debit_amount'    => 0,
                'credit_amount'   => $credit,
                'balance'         => $balance,
                'reference'       => 'UTR:' . strtoupper(substr(md5($i . $settlementRef), 0, 22)),
                'bank_name'       => 'HDFC Bank',
                'created_at'      => $date->toDateTimeString(),
            ];
        }

        // Various debits (office expenses, shipping supplies, etc.)
        $debits = [
            ['AMAZON BUSINESS PURCHASE', 4299],
            ['FLIPKART SELLER SUPPLIES', 1899],
            ['BLUEDART COURIER CHARGES', 3420],
            ['GOOGLE ADS PAYMENT', 8500],
            ['META ADS INSTAGRAM', 5000],
            ['CANVA PRO SUBSCRIPTION', 499],
            ['SHOPIFY MONTHLY PLAN', 1994],
            ['PACKAGING MATERIALS VENDOR', 12500],
            ['CA PROFESSIONAL FEES', 5000],
            ['OFFICE RENT TRANSFER', 15000],
        ];

        foreach ($debits as $idx => [$desc, $amount]) {
            $date    = $this->start->copy()->addDays(rand(5, 85));
            $balance -= $amount;
            $rows[] = [
                'workspace_id'    => $this->workspaceId,
                'import_batch_id' => $importBatch->id,
                'transaction_date'=> $date->toDateString(),
                'value_date'      => $date->toDateString(),
                'description'     => $desc,
                'debit_amount'    => $amount,
                'credit_amount'   => 0,
                'balance'         => max(0, $balance),
                'reference'       => 'REF' . rand(100000, 999999),
                'bank_name'       => 'HDFC Bank',
                'created_at'      => $date->toDateTimeString(),
            ];
        }

        DB::table('bank_transactions')->insert($rows);
    }

    // ─── GST ─────────────────────────────────────────────────────────────

    private function seedGstTransactions(array $products): void
    {
        $importBatch = ImportBatch::create([
            'workspace_id' => $this->workspaceId,
            'user_id'      => $this->userId,
            'type'         => 'gst_report',
            'original_filename' => 'demo_gst_report.csv',
            'status'       => 'completed',
        ]);

        $rows     = [];
        $invoiceN = 1001;

        foreach ($products as $product) {
            for ($i = 0; $i < rand(8, 20); $i++) {
                $date        = Carbon::createFromTimestamp(rand($this->start->timestamp, $this->end->timestamp));
                $price       = (float) $product->price;
                $qty         = rand(1, 3);
                $taxableVal  = round($price * $qty / 1.18, 2);
                $isInterState = rand(0, 1) === 1;

                $rows[] = [
                    'workspace_id'    => $this->workspaceId,
                    'import_batch_id' => $importBatch->id,
                    'transaction_type'=> rand(0, 9) === 0 ? 'RETURN' : 'SALE',
                    'invoice_date'    => $date->toDateString(),
                    'invoice_number'  => 'IN-2024-' . str_pad($invoiceN++, 6, '0', STR_PAD_LEFT),
                    'order_id'        => '403-' . rand(1000000, 9999999) . '-' . rand(1000000, 9999999),
                    'asin'            => $product->asin,
                    'sku'             => $product->sku,
                    'product_name'    => mb_substr($product->title, 0, 80),
                    'quantity'        => $qty,
                    'ship_from_state' => 'Maharashtra',
                    'ship_to_state'   => $isInterState ? $this->randomState() : 'Maharashtra',
                    'taxable_value'   => $taxableVal,
                    'igst_rate'       => $isInterState ? 18.00 : null,
                    'igst_amount'     => $isInterState ? round($taxableVal * 0.18, 2) : null,
                    'cgst_rate'       => !$isInterState ? 9.00 : null,
                    'cgst_amount'     => !$isInterState ? round($taxableVal * 0.09, 2) : null,
                    'sgst_rate'       => !$isInterState ? 9.00 : null,
                    'sgst_amount'     => !$isInterState ? round($taxableVal * 0.09, 2) : null,
                    'invoice_amount'  => $price * $qty,
                    'hsn_sac'         => $this->hsnCode($product->category),
                    'created_at'      => $date->toDateTimeString(),
                ];
            }
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('gst_transactions')->insert($chunk);
        }
    }

    // ─── Competitors ──────────────────────────────────────────────────────

    private function seedCompetitors(array $products): void
    {
        // Add 3 competitors for the first 8 products
        $competitors = [
            // Earbuds competitors
            ['asin' => 'B09COMP0001', 'title' => 'boAt Airdopes 141 Bluetooth TWS Earbuds | 42Hr Playback | ENx Technology | IWP | IPX4 Water Resistant | Bluetooth v5.1 | Beast Mode', 'brand' => 'boAt', 'price' => 1299, 'rating' => 4.1, 'review_count' => 45231],
            ['asin' => 'B09COMP0002', 'title' => 'Noise Buds VS104 Truly Wireless Earbuds | 32 Hours Playtime | 13mm Drivers | Environmental Noise Cancellation | IPX5 Rated', 'brand' => 'Noise', 'price' => 999, 'rating' => 3.9, 'review_count' => 23456],
            ['asin' => 'B09COMP0003', 'title' => 'pTron Bassbuds Pro True Wireless Stereo Earphones | Digital LED Display | 32H Battery | Gaming Mode | IPX4 Earbuds | Bluetooth 5.1', 'brand' => 'pTron', 'price' => 799, 'rating' => 3.8, 'review_count' => 18900],

            // Laptop stand competitors
            ['asin' => 'B09COMP0004', 'title' => 'Portronics My Buddy K3 Laptop Stand | Height & Angle Adjustable | Aluminium Alloy | Compatible with 10-16 inch MacBook & Laptops', 'brand' => 'Portronics', 'price' => 1299, 'rating' => 4.2, 'review_count' => 5432],
            ['asin' => 'B09COMP0005', 'title' => 'AmazonBasics Portable Laptop Stand | Height Adjustable | Aluminium | Compatible with MacBook Air/Pro, Dell, HP, Lenovo', 'brand' => 'Amazon Basics', 'price' => 1199, 'rating' => 4.0, 'review_count' => 8765],

            // Yoga mat competitors
            ['asin' => 'B09COMP0006', 'title' => 'Boldfit Yoga Mat for Men & Women | 6mm Non Slip Yoga Mats | Anti Skid TPE Material | Extra Thick Exercise Mat with Carrying Strap', 'brand' => 'Boldfit', 'price' => 799, 'rating' => 4.2, 'review_count' => 34567],
            ['asin' => 'B09COMP0007', 'title' => 'Strauss Anti-Slip Yoga Mat 6mm | Natural Material | Printed Design | Exercise Mat for Gym & Workout | Includes Carry Bag', 'brand' => 'Strauss', 'price' => 699, 'rating' => 4.0, 'review_count' => 21234],

            // Mug competitors
            ['asin' => 'B09COMP0008', 'title' => 'CLAY CRAFT INDIA Handmade Ceramic Coffee Mug 300ml | Microwave Safe | Tea Mug | Dishwasher Safe | Gift for Men & Women', 'brand' => 'Clay Craft', 'price' => 349, 'rating' => 4.3, 'review_count' => 7654],
            ['asin' => 'B09COMP0009', 'title' => 'Nestasia White Fluted Coffee Mug 300ml | Ceramic Mug | Microwave & Dishwasher Safe | Handmade | Perfect for Office & Home Use', 'brand' => 'Nestasia', 'price' => 499, 'rating' => 4.5, 'review_count' => 3210],
        ];

        $importBatch = ImportBatch::create([
            'workspace_id' => $this->workspaceId,
            'user_id'      => $this->userId,
            'type'         => 'competitors_csv',
            'original_filename' => 'demo_competitors.csv',
            'status'       => 'completed',
        ]);

        // Link competitors to products
        $competitorData = [
            0 => [0, 1, 2],  // Earbuds → 3 competitors
            2 => [3, 4],      // Laptop stand → 2 competitors
            5 => [5, 6],      // Yoga mat → 2 competitors
            10 => [7, 8],     // Mug → 2 competitors
        ];

        foreach ($competitorData as $productIdx => $compIndexes) {
            if (!isset($products[$productIdx])) continue;
            $product = $products[$productIdx];

            foreach ($compIndexes as $compIdx) {
                if (!isset($competitors[$compIdx])) continue;
                $comp = $competitors[$compIdx];

                Competitor::updateOrCreate(
                    [
                        'workspace_id' => $this->workspaceId,
                        'product_id'   => $product->id,
                        'asin'         => $comp['asin'],
                    ],
                    [
                        'import_batch_id' => $importBatch->id,
                        'title'           => $comp['title'],
                        'brand'           => $comp['brand'],
                        'bullet_1'        => 'Competitor has strong presence with ' . $comp['review_count'] . ' reviews at ₹' . $comp['price'],
                        'bullet_2'        => 'Popular choice in the ' . $product->category . ' category on Amazon India',
                        'description'     => 'Competitive product in the ' . $product->category . ' space with significant market share.',
                        'price'           => $comp['price'],
                        'currency'        => 'INR',
                        'rating'          => $comp['rating'],
                        'review_count'    => $comp['review_count'],
                        'source_type'     => 'csv',
                    ]
                );
            }
        }
    }

    // ─── Product Analysis (scoring) ──────────────────────────────────────

    private function analyzeProducts(array $products): void
    {
        $service = app(ProductIntelligenceService::class);

        foreach ($products as $product) {
            try {
                $service->analyze($product->fresh());
            } catch (\Throwable $e) {
                // Continue if AI fails (no API key)
            }
        }
    }

    // ─── Reconciliation ──────────────────────────────────────────────────

    private function runReconciliation(): void
    {
        $run = ReconciliationRun::create([
            'workspace_id' => $this->workspaceId,
            'user_id'      => $this->userId,
            'period_start' => $this->start->toDateString(),
            'period_end'   => $this->end->toDateString(),
            'status'       => 'pending',
        ]);

        try {
            $engine = app(ReconciliationEngine::class);
            $engine->run($run);
        } catch (\Throwable $e) {
            $this->command->warn("Reconciliation partial: " . $e->getMessage());
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function randomCity(): string
    {
        return collect(['Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad', 'Chennai', 'Kolkata',
            'Pune', 'Ahmedabad', 'Jaipur', 'Surat', 'Lucknow', 'Kanpur', 'Nagpur',
            'Indore', 'Thane', 'Bhopal', 'Visakhapatnam', 'Pimpri-Chinchwad', 'Patna', 'Vadodara'])->random();
    }

    private function randomState(): string
    {
        return collect(['Maharashtra', 'Karnataka', 'Tamil Nadu', 'Delhi', 'Gujarat',
            'Telangana', 'West Bengal', 'Uttar Pradesh', 'Rajasthan', 'Kerala',
            'Madhya Pradesh', 'Punjab', 'Haryana', 'Bihar', 'Odisha'])->random();
    }

    private function hsnCode(string $category): string
    {
        return match(true) {
            str_contains($category, 'Electronics')  => '8518',
            str_contains($category, 'Kitchen')       => '7323',
            str_contains($category, 'Sports')        => '9506',
            str_contains($category, 'Personal Care') => '3305',
            str_contains($category, 'Home')          => '9405',
            str_contains($category, 'Stationery')    => '4820',
            str_contains($category, 'Bags')          => '4202',
            str_contains($category, 'Bedding')       => '9404',
            default                                  => '9999',
        };
    }
}
