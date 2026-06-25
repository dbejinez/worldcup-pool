<?php

namespace App\Support;

/**
 * Maps team/country names to ISO codes used by the flag-icons library
 * (lowercase ISO 3166-1 alpha-2, plus gb-eng/gb-sct/gb-wls subdivisions).
 */
class CountryFlags
{
    /** @var array<string, string> */
    private const MAP = [
        'mexico' => 'mx',
        'south africa' => 'za',
        'south korea' => 'kr',
        'korea republic' => 'kr',
        'czechia' => 'cz',
        'czech republic' => 'cz',
        'canada' => 'ca',
        'bosnia and herzegovina' => 'ba',
        'bosnia' => 'ba',
        'qatar' => 'qa',
        'switzerland' => 'ch',
        'brazil' => 'br',
        'morocco' => 'ma',
        'haiti' => 'ht',
        'scotland' => 'gb-sct',
        'united states' => 'us',
        'united states of america' => 'us',
        'usa' => 'us',
        'paraguay' => 'py',
        'australia' => 'au',
        'türkiye' => 'tr',
        'turkiye' => 'tr',
        'turkey' => 'tr',
        'germany' => 'de',
        'curaçao' => 'cw',
        'curacao' => 'cw',
        'ivory coast' => 'ci',
        "cote d'ivoire" => 'ci',
        'côte d’ivoire' => 'ci',
        'ecuador' => 'ec',
        'netherlands' => 'nl',
        'japan' => 'jp',
        'sweden' => 'se',
        'tunisia' => 'tn',
        'belgium' => 'be',
        'egypt' => 'eg',
        'iran' => 'ir',
        'new zealand' => 'nz',
        'spain' => 'es',
        'cabo verde' => 'cv',
        'cape verde' => 'cv',
        'saudi arabia' => 'sa',
        'uruguay' => 'uy',
        'france' => 'fr',
        'iraq' => 'iq',
        'norway' => 'no',
        'senegal' => 'sn',
        'argentina' => 'ar',
        'algeria' => 'dz',
        'austria' => 'at',
        'jordan' => 'jo',
        'portugal' => 'pt',
        'dr congo' => 'cd',
        'democratic republic of the congo' => 'cd',
        'uzbekistan' => 'uz',
        'colombia' => 'co',
        'croatia' => 'hr',
        'england' => 'gb-eng',
        'ghana' => 'gh',
        'panama' => 'pa',
        'wales' => 'gb-wls',
    ];

    public static function codeFor(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return self::MAP[mb_strtolower(trim($name))] ?? null;
    }
}
