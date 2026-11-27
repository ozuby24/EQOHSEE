<?php

namespace App\Support\Sso;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * Masuk dengan akun kerja/sekolah Microsoft (Microsoft Entra ID), lewat
 * platform identitas Microsoft v2 dan Microsoft Graph.
 *
 * Ditulis sendiri di atas Laravel Socialite — bukan paket penyedia pihak
 * ketiga — supaya seluruh aturannya terbaca di satu berkas ini.
 *
 * ── SUREL HANYA DIPERCAYA BILA DOMAINNYA MILIK PENYEWA ITU ──
 *
 * Atribut `mail` pada Entra dapat diisi bebas oleh admin penyewa mana
 * pun. Penyerang yang membuat penyewanya sendiri dapat mengisi `mail`
 * dengan alamat korban dan, bila alamat itu dipercaya begitu saja, masuk
 * sebagai korban (kerentanan "nOAuth"). Karena itu surelnya baru diakui
 * bila domainnya termasuk `verifiedDomains` penyewa yang sedang masuk —
 * domain yang kepemilikannya sudah dibuktikan penyewa itu ke Microsoft.
 * Akun Microsoft pribadi tidak punya penyewa organisasi dan ditolak.
 */
class PenyediaMicrosoft extends AbstractProvider
{
    protected $scopes = ['openid', 'profile', 'email', 'User.Read'];

    protected $scopeSeparator = ' ';

    /** Penyewa: GUID atau nama domain penyewa, 'organizations', atau 'common'. */
    protected function penyewa(): string
    {
        $t = (string) (config('services.microsoft.tenant') ?: 'organizations');

        return preg_match('/^[A-Za-z0-9.-]{1,64}$/', $t) ? $t : 'organizations';
    }

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            'https://login.microsoftonline.com/'.$this->penyewa().'/oauth2/v2.0/authorize', $state,
        );
    }

    protected function getTokenUrl()
    {
        return 'https://login.microsoftonline.com/'.$this->penyewa().'/oauth2/v2.0/token';
    }

    protected function getUserByToken($token)
    {
        $tajuk = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        $saya = json_decode((string) $this->getHttpClient()->get(
            'https://graph.microsoft.com/v1.0/me?$select=id,displayName,mail,userPrincipalName',
            ['headers' => $tajuk],
        )->getBody(), true) ?: [];

        // Domain terverifikasi penyewa. Gagal dibaca (akun pribadi, izin
        // dicabut) berarti tidak ada domain yang dapat dipercaya.
        try {
            $org = json_decode((string) $this->getHttpClient()->get(
                'https://graph.microsoft.com/v1.0/organization?$select=verifiedDomains',
                ['headers' => $tajuk],
            )->getBody(), true) ?: [];
        } catch (\Throwable) {
            $org = [];
        }

        $saya['domain_terverifikasi'] = collect($org['value'][0]['verifiedDomains'] ?? [])
            ->pluck('name')->filter()->map(fn ($d) => strtolower((string) $d))->values()->all();

        return $saya;
    }

    protected function mapUserToObject(array $user)
    {
        $surel = strtolower((string) ($user['mail'] ?? $user['userPrincipalName'] ?? ''));
        $domain = str_contains($surel, '@') ? substr($surel, strrpos($surel, '@') + 1) : '';

        return (new User)->setRaw($user)->map([
            'id'    => $user['id'] ?? null,
            'name'  => $user['displayName'] ?? null,
            // Surel yang domainnya tidak dimiliki penyewanya tidak dikembalikan.
            'email' => $domain !== '' && in_array($domain, $user['domain_terverifikasi'] ?? [], true) ? $surel : null,
        ]);
    }
}
