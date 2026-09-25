<?php

use App\Models\User;
use App\Modules\Players\Actions\UpdatePlayerAvatar;
use App\Modules\Players\DTO\PlayerAvatarData;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests cannot view upload or remove avatars', function () {
    Storage::fake('avatars');
    $player = User::factory()->create(['avatar_path' => 'existing.webp']);
    Storage::disk('avatars')->put('existing.webp', 'private avatar');

    $this->get(route('players.avatar.show', $player->username))->assertRedirect(route('login'));
    $this->post(route('players.avatar.store'), ['avatar' => UploadedFile::fake()->image('avatar.png')])->assertRedirect(route('login'));
    $this->delete(route('players.avatar.destroy'))->assertRedirect(route('login'));

    expect($player->refresh()->avatar_path)->toBe('existing.webp');
    Storage::disk('avatars')->assertExists('existing.webp');
});

test('players upload a resized private WebP and see it across their card and header', function (string $extension) {
    Storage::fake('avatars');
    Storage::fake('public');
    $player = User::factory()->create();
    $image = UploadedFile::fake()->image('photo.'.$extension, 1600, 800);
    $file = UploadedFile::fake()->createWithContent('photo.'.$extension, $image->getContent().'private-original-metadata');

    $this->actingAs($player)->from(route('players.show', $player->username))
        ->post(route('players.avatar.store'), ['avatar' => $file])
        ->assertRedirect(route('players.show', $player->username))->assertSessionHasNoErrors();

    $path = $player->refresh()->avatar_path;
    expect($path)->toStartWith($player->id.'/')->toEndWith('.webp');
    $disk = Storage::disk('avatars');
    $disk->assertExists($path);
    expect($disk->allFiles())->toBe([$path]);
    Storage::disk('public')->assertDirectoryEmpty('/');
    $encoded = $disk->get($path);
    expect($encoded)->not->toContain('private-original-metadata');
    $dimensions = getimagesizefromstring($encoded);
    expect([$dimensions[0], $dimensions[1], $dimensions['mime']])->toBe([512, 256, 'image/webp']);
    expect(strlen($encoded))->toBeLessThan(2048 * 1024);

    $this->get(route('players.show', $player->username))->assertInertia(fn (Assert $page) => $page
        ->where('player.avatarVersion', $player->avatarVersion())
        ->where('auth.user.avatarVersion', $player->avatarVersion())
        ->where('avatarLimits.max_kilobytes', 2048)
        ->missing('player.avatar_path')->missing('auth.user.avatar_path')
    );
})->with(['jpeg', 'png', 'webp']);

test('invalid uploads leave the existing avatar untouched', function (string $case, string $message) {
    Storage::fake('avatars');
    $player = User::factory()->create(['avatar_path' => 'existing.webp']);
    Storage::disk('avatars')->put('existing.webp', 'old avatar');
    $file = match ($case) {
        'missing' => null,
        'path' => 'avatars/another-player.webp',
        'large' => UploadedFile::fake()->image('large.png')->size(2049),
        'wide' => UploadedFile::fake()->image('wide.png', 3840, 10),
        'tall' => UploadedFile::fake()->image('tall.png', 10, 3840),
        'svg' => UploadedFile::fake()->createWithContent('avatar.jpg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'text' => UploadedFile::fake()->createWithContent('avatar.png', '<?php echo "not an image";'),
        'gif' => UploadedFile::fake()->image('animated.gif'),
        'broken' => UploadedFile::fake()->createWithContent('broken.png', substr(UploadedFile::fake()->image('ok.png')->getContent(), 0, 33)),
    };

    $upload = in_array($case, ['svg', 'text'], true)
        ? new UploadedFile($file->getPathname(), $file->getClientOriginalName(), test: true)
        : $file;

    $this->actingAs($player)->post(route('players.avatar.store'), ['avatar' => $upload])
        ->assertSessionHasErrors(['avatar' => $message]);

    expect($player->refresh()->avatar_path)->toBe('existing.webp');
    expect(Storage::disk('avatars')->allFiles())->toBe(['existing.webp']);
    expect(Storage::disk('avatars')->get('existing.webp'))->toBe('old avatar');
})->with([
    'missing' => ['missing', 'Выбери изображение для аватара.'],
    'path instead of upload' => ['path', 'Выбери изображение JPG, PNG или WebP.'],
    'over 2 MB' => ['large', 'Аватар должен быть не больше 2 МБ.'],
    '4K width' => ['wide', 'Изображение должно быть не больше 2048 × 2048 пикселей.'],
    '4K height' => ['tall', 'Изображение должно быть не больше 2048 × 2048 пикселей.'],
    'SVG disguised as JPG' => ['svg', 'Выбери изображение JPG, PNG или WebP.'],
    'text disguised as PNG' => ['text', 'Выбери изображение JPG, PNG или WebP.'],
    'GIF' => ['gif', 'Выбери изображение JPG, PNG или WebP.'],
    'valid header but corrupt image' => ['broken', 'Не удалось прочитать изображение. Выбери другую картинку.'],
]);

test('small transparent avatars are not enlarged and retain transparency', function () {
    Storage::fake('avatars');
    $player = User::factory()->create();
    $image = imagecreatetruecolor(40, 20);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    ob_start();
    imagepng($image);
    $file = UploadedFile::fake()->createWithContent('transparent.png', ob_get_clean());

    $this->actingAs($player)->post(route('players.avatar.store'), ['avatar' => $file])->assertSessionHasNoErrors();

    $stored = Storage::disk('avatars')->get($player->refresh()->avatar_path);
    $thumbnail = imagecreatefromstring($stored);
    expect([imagesx($thumbnail), imagesy($thumbnail)])->toBe([40, 20]);
    expect(imagecolorsforindex($thumbnail, imagecolorat($thumbnail, 0, 0))['alpha'])->toBe(127);
});

test('portrait phone photos are oriented before resizing and their EXIF is removed', function () {
    Storage::fake('avatars');
    $player = User::factory()->create();
    $jpeg = UploadedFile::fake()->image('phone.jpg', 1200, 600)->getContent();
    $exif = "Exif\0\0".'II'.pack('vVv', 42, 8, 1).pack('vvVvvV', 0x0112, 3, 1, 6, 0, 0);
    $jpeg = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

    $this->actingAs($player)->post(route('players.avatar.store'), [
        'avatar' => UploadedFile::fake()->createWithContent('phone.jpg', $jpeg),
    ])->assertSessionHasNoErrors();

    $stored = Storage::disk('avatars')->get($player->refresh()->avatar_path);
    $dimensions = getimagesizefromstring($stored);
    expect([$dimensions[0], $dimensions[1]])->toBe([256, 512]);
    expect($stored)->not->toContain('Exif');
});

test('replacing and removing avatars affect only the signed in player and delete old files', function () {
    Storage::fake('avatars');
    $player = User::factory()->create(['avatar_path' => 'old.webp']);
    $other = User::factory()->create(['avatar_path' => 'other.webp']);
    $disk = Storage::disk('avatars');
    $disk->put('old.webp', 'old');
    $disk->put('other.webp', 'other');
    $previousVersion = $player->avatarVersion();

    $this->actingAs($player)->post(route('players.avatar.store'), [
        'avatar' => UploadedFile::fake()->image('avatar.png'),
        'user_id' => $other->id, 'id' => $other->id, 'avatar_path' => 'other.webp',
    ])->assertSessionHasNoErrors();

    $currentPath = $player->refresh()->avatar_path;
    expect($player->avatarVersion())->not->toBe($previousVersion);
    $disk->assertMissing('old.webp');
    $disk->assertExists($currentPath);

    $this->from(route('profile.edit'))->delete(route('players.avatar.destroy'), ['user_id' => $other->id])
        ->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
    expect($player->refresh()->avatar_path)->toBeNull();
    $disk->assertMissing($currentPath);
    expect($other->refresh()->avatar_path)->toBe('other.webp');
    expect($disk->allFiles())->toBe(['other.webp']);

    $this->delete(route('players.avatar.destroy'))->assertSessionHasNoErrors();
    $this->get(route('players.avatar.show', $player->username))->assertNotFound();
});

test('signed in viewers receive only the selected players private avatar', function () {
    Storage::fake('avatars');
    $viewer = User::factory()->create();
    $player = User::factory()->create(['avatar_path' => 'player.webp']);
    Storage::disk('avatars')->put('player.webp', 'player-image');
    Storage::disk('avatars')->put('secret.webp', 'secret');

    $response = $this->actingAs($viewer)->get(route('players.avatar.show', [
        'user' => $player->username, 'path' => 'secret.webp',
    ]))->assertOk()->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->streamedContent())->toBe('player-image');
    $this->get(route('players.show', $player->username))->assertInertia(fn (Assert $page) => $page
        ->where('avatarLimits', null)->where('isOwner', false)
        ->where('player.avatarVersion', $player->avatarVersion())->missing('player.avatar_path')
    );
    $this->get(route('players.avatar.show', $viewer->username))->assertNotFound();
    Storage::disk('avatars')->delete('player.webp');
    $this->get(route('players.avatar.show', $player->username))->assertNotFound();
    $this->get('/storage/avatars/secret.webp')->assertForbidden();
    $this->get('/storage/secret.webp')->assertForbidden();
});

test('a failed database update keeps the old avatar and removes the new file', function () {
    Storage::fake('avatars');
    $player = User::factory()->create(['avatar_path' => 'old.webp']);
    Storage::disk('avatars')->put('old.webp', 'old image');
    $file = UploadedFile::fake()->image('avatar.png');
    DB::statement("CREATE TRIGGER reject_avatar BEFORE UPDATE OF avatar_path ON users BEGIN SELECT RAISE(ABORT, 'avatar update rejected'); END");

    expect(fn () => app(UpdatePlayerAvatar::class)->handle($player, new PlayerAvatarData($file->getPathname())))
        ->toThrow(QueryException::class);

    expect($player->refresh()->avatar_path)->toBe('old.webp');
    expect(Storage::disk('avatars')->allFiles())->toBe(['old.webp']);
});

test('a storage failure leaves the current avatar usable and returns a form error', function () {
    Storage::fake('avatars');
    $player = User::factory()->create(['avatar_path' => 'old.webp']);
    $disk = Storage::disk('avatars');
    $disk->put('old.webp', 'old image');
    $disk->put((string) $player->id, 'A file blocks creation of the upload directory');

    $this->actingAs($player)->post(route('players.avatar.store'), ['avatar' => UploadedFile::fake()->image('avatar.png')])
        ->assertSessionHasErrors(['avatar' => 'Не удалось сохранить аватар. Попробуй ещё раз.']);

    expect($player->refresh()->avatar_path)->toBe('old.webp');
    expect($disk->get('old.webp'))->toBe('old image');
});

test('deleting the account also deletes its private avatar', function () {
    Storage::fake('avatars');
    $player = User::factory()->create(['avatar_path' => 'avatar.webp']);
    Storage::disk('avatars')->put('avatar.webp', 'image');

    $this->actingAs($player)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

    $this->assertModelMissing($player);
    Storage::disk('avatars')->assertMissing('avatar.webp');
});
