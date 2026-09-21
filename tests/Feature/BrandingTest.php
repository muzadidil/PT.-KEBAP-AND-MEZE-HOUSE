<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Appearance;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tampilan halaman masuk yang bisa diatur pemilik.
 *
 * Yang dijaga di sini: halaman masuk tidak pernah tampil kosong. Apa pun
 * yang dilakukan pemilik — belum mengatur apa-apa, menghapus semua slide,
 * atau berkas gambarnya hilang dari disk — halaman itu tetap punya latar
 * dan tetap punya identitas.
 */
class BrandingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Branding::DISK);
        Setting::forget();
    }

    public function test_tanpa_pengaturan_halaman_masuk_memakai_gambar_bawaan(): void
    {
        $slides = Branding::slides();

        $this->assertCount(3, $slides);
        $this->assertStringContainsString('login-kebab.svg', $slides[0]['image']);
        $this->assertNotEmpty($slides[0]['title']);
        $this->assertNull(Branding::logoUrl());
    }

    public function test_halaman_masuk_menampilkan_ketiga_latar_bawaan(): void
    {
        $response = $this->get(route('filament.cashier.auth.login'))->assertOk();

        $response->assertSee('login-kebab.svg', false);
        $response->assertSee('login-meze.svg', false);
        $response->assertSee('login-tea.svg', false);
        $response->assertSee('auth__visual', false);
    }

    public function test_nama_dan_tagline_bisa_diganti(): void
    {
        Setting::put('brand.name', 'Meze Corner');
        Setting::put('brand.tagline', 'Cabang Kemang');

        $this->assertSame('Meze Corner', Branding::businessName());
        $this->assertSame('Cabang Kemang', Branding::tagline());
        $this->assertSame('M', Branding::initial());

        $this->get(route('filament.cashier.auth.login'))
            ->assertOk()
            ->assertSee('Meze Corner')
            ->assertSee('Cabang Kemang');
    }

    public function test_super_admin_bisa_mengunggah_logo_dan_latar(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Appearance::class)
            ->fillForm([
                'name' => 'Kebap House',
                'tagline' => 'Dapur Turki',
                // FileUpload menyimpan state-nya sebagai larik, jadi
                // berkasnya dibungkus larik walau cuma satu.
                'logo' => [UploadedFile::fake()->image('logo.png')],
                'slides' => [
                    [
                        'image' => [UploadedFile::fake()->image('latar.jpg', 1600, 900)],
                        'eyebrow' => 'Panggangan',
                        'title' => 'Kebab segar tiap hari',
                        'text' => 'Dari panggangan arang langsung ke meja.',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Kebap House', Branding::businessName());
        $this->assertNotNull(Branding::logoUrl());

        $slides = Branding::slides();
        $this->assertCount(1, $slides);
        $this->assertSame('Kebab segar tiap hari', $slides[0]['title']);
        $this->assertNotNull($slides[0]['image']);

        // Berkasnya benar-benar mendarat di disk, di dalam foldernya.
        $stored = Setting::get('brand.slides')[0]['image'];
        $this->assertStringStartsWith('slides/', $stored);
        Storage::disk(Branding::DISK)->assertExists($stored);
    }

    /**
     * Unggahan disajikan langsung dari public/, bukan lewat storage:link.
     * Shared hosting sering menolak symlink, dan latar halaman masuk harus
     * bisa dibuka orang yang belum masuk sama sekali.
     */
    public function test_berkas_branding_disajikan_dari_public_tanpa_symlink(): void
    {
        $this->assertSame('/uploads/branding', config('filesystems.disks.branding.url'));
        $this->assertSame(public_path('uploads/branding'), config('filesystems.disks.branding.root'));
    }

    public function test_menghapus_semua_slide_mengembalikan_gambar_bawaan(): void
    {
        Setting::put('brand.slides', [
            ['image' => 'slides/satu.jpg', 'title' => 'Satu'],
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(Appearance::class)
            ->fillForm(['slides' => []])
            ->call('save');

        // Halaman masuk tanpa latar sama sekali hanya akan tampak rusak.
        $slides = Branding::slides();
        $this->assertCount(3, $slides);
        $this->assertStringContainsString('login-kebab.svg', $slides[0]['image']);
    }

    public function test_slide_kosong_tidak_ikut_tersimpan(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Appearance::class)
            ->fillForm([
                'slides' => [
                    ['image' => null, 'eyebrow' => null, 'title' => null, 'text' => null],
                    ['image' => null, 'eyebrow' => null, 'title' => 'Ada isinya', 'text' => null],
                ],
            ])
            ->call('save');

        $this->assertCount(1, Setting::get('brand.slides'));
    }

    public function test_gambar_yang_hilang_dari_disk_tidak_membuat_gambar_rusak(): void
    {
        // Berkas bisa terhapus dari disk tanpa lewat aplikasi; kalau itu
        // terjadi, halaman masuk harus jatuh ke bawaan, bukan menampilkan
        // ikon gambar rusak di layar penuh.
        Setting::put('brand.logo', 'logo/hilang.png');
        Setting::put('brand.slides', [['image' => 'slides/hilang.jpg', 'title' => null, 'text' => null]]);

        $this->assertNull(Branding::logoUrl());

        $slides = Branding::slides();
        $this->assertCount(3, $slides);
        $this->assertStringContainsString('login-kebab.svg', $slides[0]['image']);
    }

    /** Tampilan termasuk pengaturan, jadi milik Super Admin saja. */
    public function test_halaman_tampilan_hanya_untuk_super_admin(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('filament.admin.pages.appearance'))
            ->assertOk();

        // Ganti akun berarti sesi baru; tanpa ini, pengaman sesi Laravel
        // mengeluarkan akun kedua sebelum hak aksesnya sempat diperiksa.
        $this->flushSession();

        $this->actingAs($this->admin())
            ->get(route('filament.admin.pages.appearance'))
            ->assertForbidden();

        $this->actingAs($this->cashier())
            ->get(route('filament.admin.pages.appearance'))
            ->assertForbidden();
    }

    public function test_perubahan_langsung_terlihat_tanpa_menunggu_cache(): void
    {
        $this->get(route('filament.cashier.auth.login'))->assertOk();

        Setting::put('brand.tagline', 'Tagline baru');

        // Cache pengaturan memakai rememberForever; kalau tidak digugurkan
        // saat disimpan, perubahannya tidak akan pernah terlihat.
        $this->get(route('filament.cashier.auth.login'))
            ->assertOk()
            ->assertSee('Tagline baru');
    }
}
