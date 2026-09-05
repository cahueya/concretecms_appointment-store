<?php
namespace Concrete\Package\AppointmentStore\Security;

use Concrete\Core\Application\Application;

class CredentialCipher
{
    private const CONFIG_KEY = 'appointment_store::security.key';

    /** @var Application */
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function ensureKey(): string
    {
        $this->assertSodium();
        $config = $this->app->make('config');
        $encoded = (string) $config->get(self::CONFIG_KEY, '');
        if ($encoded !== '') {
            $key = base64_decode($encoded, true);
            if (is_string($key) && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $key;
            }
        }

        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $config->save(self::CONFIG_KEY, base64_encode($key));
        return $key;
    }

    public function encrypt(string $plaintext): string
    {
        $key = $this->ensureKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $this->assertSodium();
        $payload = base64_decode($encoded, true);
        if (!is_string($payload) || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException(t('The stored CalDAV credential is invalid.'));
        }

        $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->ensureKey());
        if ($plaintext === false) {
            throw new \RuntimeException(t('The stored CalDAV credential could not be decrypted.'));
        }
        return $plaintext;
    }

    private function assertSodium(): void
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new \RuntimeException(t('Appointment Store requires the PHP sodium extension.'));
        }
    }
}
