<?php
declare(strict_types=1);

/**
 * A small character kit for the careers illustrations: people drawn as inline SVG, with proportions, faces, hair, clothing shading and poses.
 * Every figure is a <g> whose colours are CSS custom properties (--skin, --hair, --top ...), so one drawing serves every person and follows
 * the page's light and dark themes (assets/css/careers.css gives each variable a value).
 *
 * Coordinates: a standing figure has its feet on y = 0 and the middle between the feet on x = 0; it is about 330 units tall.
 * Place it with <g transform="translate(x y) scale(s)">. Bust figures (head and shoulders) have the neck base on y = 0.
 *
 * Poses: present (pointing at something, a tablet in the other hand), tablet (a mug in one hand, a tablet in the other), rest (arms down),
 *        cheer (both arms up), wave (an envelope held out), scope (arms forward, at a telescope), phone, seated (at a desk, hands forward).
 */
final class CareersPeople
{
    /** Looks. Each: skin, skin shade, hair, hair highlight, top, top shade, bottoms, shoes, plus hair style and extras. */
    public const LOOKS = [
        'amara' => ['skin' => '#8A5638', 'skin_s' => '#6B3F27', 'hair' => '#1A120E', 'hair_h' => '#4A3226', 'top' => '#B5482A', 'top_s' => '#8E3820', 'inner' => '#F3EBDD', 'bot' => '#27303F', 'bot_s' => '#1B222D', 'shoe' => '#F3EBDD', 'style' => 'afro', 'glasses' => false],
        'lea'   => ['skin' => '#EDBE9A', 'skin_s' => '#D39A74', 'hair' => '#2B1B15', 'hair_h' => '#5A3B2D', 'top' => '#2F5740', 'top_s' => '#1F3D2B', 'inner' => '#F3EBDD', 'bot' => '#3A3946', 'bot_s' => '#272633', 'shoe' => '#B5482A', 'style' => 'bob', 'glasses' => false],
        'marc'  => ['skin' => '#C98F66', 'skin_s' => '#A56F4B', 'hair' => '#17110E', 'hair_h' => '#40302A', 'top' => '#D9A23B', 'top_s' => '#B4801F', 'inner' => '#F3EBDD', 'bot' => '#2C3342', 'bot_s' => '#1F2531', 'shoe' => '#3B2A22', 'style' => 'short', 'glasses' => true],
        'nora'  => ['skin' => '#F3CFAF', 'skin_s' => '#DDA983', 'hair' => '#B5622B', 'hair_h' => '#D88444', 'top' => '#26325B', 'top_s' => '#18213F', 'inner' => '#F3EBDD', 'bot' => '#BFA27E', 'bot_s' => '#9C8260', 'shoe' => '#26325B', 'style' => 'bun', 'glasses' => false],
        'idris' => ['skin' => '#6E4630', 'skin_s' => '#523322', 'hair' => '#100B09', 'hair_h' => '#35261E', 'top' => '#F3EBDD', 'top_s' => '#D8CCB6', 'inner' => '#B5482A', 'bot' => '#2F5740', 'bot_s' => '#1F3D2B', 'shoe' => '#1B222D', 'style' => 'fade', 'glasses' => false],
    ];

    public static function vars(string $who): string
    {
        $l = self::LOOKS[$who] ?? self::LOOKS['amara'];
        return '--skin:' . $l['skin'] . ';--skin-s:' . $l['skin_s'] . ';--hair:' . $l['hair'] . ';--hair-h:' . $l['hair_h'] . ';--top:' . $l['top'] . ';--top-s:' . $l['top_s']
             . ';--inner:' . $l['inner'] . ';--bot:' . $l['bot'] . ';--bot-s:' . $l['bot_s'] . ';--shoe:' . $l['shoe'];
    }

    private static function look(string $who): array
    {
        return self::LOOKS[$who] ?? self::LOOKS['amara'];
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Head
    // -----------------------------------------------------------------------------------------------------------------

    /** Head with hair, centred on (0,0): about 40 wide and 52 tall. $tilt rotates it (degrees), $look is 'front' or 'smile'. */
    public static function head(string $who, float $tilt = 0, bool $smile = true): string
    {
        $l = self::look($who);
        $back = '';
        $front = '';
        switch ($l['style']) {
            case 'afro':
                $back = '<circle class="pp-hair" cx="0" cy="-8" r="34"/><path class="pp-hair-h" d="M-24-26C-14-38 14-38 24-26" fill="none" stroke-width="3" stroke-linecap="round"/><circle class="pp-hair" cx="-26" cy="-14" r="11"/><circle class="pp-hair" cx="26" cy="-14" r="11"/><circle class="pp-hair" cx="-12" cy="-34" r="13"/><circle class="pp-hair" cx="13" cy="-34" r="13"/>';
                $front = '<path class="pp-hair" d="M-20-8C-21-30 21-30 20-8C16-19 10-23 0-23C-10-23-16-19-20-8Z"/>';
                break;
            case 'bob':
                $back = '<path class="pp-hair" d="M-25 2C-30-34 30-34 25 2L27 26Q0 36-27 26Z"/>';
                $front = '<path class="pp-hair" d="M-21-6C-20-31 21-31 21-4C16-14 6-19-6-19C-12-19-17-14-21-6Z"/><path class="pp-hair-h" d="M-12-24C-4-28 8-27 14-22" fill="none" stroke-width="2.4" stroke-linecap="round"/>';
                break;
            case 'bun':
                $back = '<circle class="pp-hair" cx="0" cy="-36" r="11"/><path class="pp-hair" d="M-22-2C-24-30 24-30 22-2Z"/>';
                $front = '<path class="pp-hair" d="M-20-5C-21-30 21-30 20-5C14-16 7-19-2-19C-9-19-15-14-20-5Z"/><path class="pp-hair-h" d="M-6-37C-1-40 5-39 8-35" fill="none" stroke-width="2.2" stroke-linecap="round"/>';
                break;
            case 'fade':
                $back = '<path class="pp-hair" d="M-21-4C-23-28 23-28 21-4Z"/>';
                $front = '<path class="pp-hair" d="M-19-9C-20-31 20-31 19-9C14-18 8-21 0-21C-8-21-14-18-19-9Z"/><path class="pp-hair-h" d="M-10-26C-3-29 6-28 11-24" fill="none" stroke-width="2.2" stroke-linecap="round"/>';
                break;
            default: // short
                $back = '';
                $front = '<path class="pp-hair" d="M-21-5C-24-31 24-31 21-5C18-14 12-19 4-20C-4-17-12-16-21-5Z"/><path class="pp-hair-h" d="M-9-26C-2-29 8-28 14-23" fill="none" stroke-width="2.4" stroke-linecap="round"/>';
        }
        $glasses = $l['glasses'] ? '<g fill="none" stroke="#1B1B22" stroke-width="1.8"><rect x="-15" y="-9" width="12.5" height="10" rx="4"/><rect x="2.500" y="-9" width="12.5" height="10" rx="4"/><path d="M-2.500-5Q0-7 2.500-5"/></g>' : '';
        $mouth = $smile ? '<path d="M-5 13Q0 18.500 5 13" fill="none" stroke="#8A3A2C" stroke-width="2" stroke-linecap="round"/><path d="M-3.200 14.200Q0 16.300 3.200 14.200" fill="#fff" opacity=".85"/>'
                        : '<path d="M-4 14Q0 16 4 14" fill="none" stroke="#8A3A2C" stroke-width="2" stroke-linecap="round"/>';
        return '<g transform="rotate(' . $tilt . ')">' . $back
            . '<path class="pp-skin-s" d="M-8 18L-8 34L8 34L8 18Z"/>'                                        // neck
            . '<ellipse class="pp-skin" cx="-20" cy="2" rx="3.600" ry="5.200"/><ellipse class="pp-skin" cx="20" cy="2" rx="3.600" ry="5.200"/>'   // ears
            . '<path class="pp-skin" d="M-19-4C-19-20-11-26 0-26C11-26 19-20 19-4C19 12 11 25 0 27C-11 25-19 12-19-4Z"/>'                         // face
            . '<path class="pp-skin-s" opacity=".30" d="M6-22C14-18 19-10 19-3C19 12 11 25 0 27C9 20 13 8 13-3C13-11 10-18 6-22Z"/>'              // shade on one side
            . '<ellipse cx="-11" cy="9" rx="4.500" ry="3" fill="#E58A7B" opacity=".35"/><ellipse cx="11" cy="9" rx="4.500" ry="3" fill="#E58A7B" opacity=".35"/>'
            . '<ellipse cx="-7" cy="-2" rx="1.900" ry="2.500" fill="#1B1B22"/><ellipse cx="7" cy="-2" rx="1.900" ry="2.500" fill="#1B1B22"/>'
            . '<circle cx="-6.400" cy="-2.900" r=".7" fill="#fff"/><circle cx="7.600" cy="-2.900" r=".7" fill="#fff"/>'
            . '<path d="M-12-8Q-7-11.500-3-8.500M3-8.500Q7-11.500 12-8" fill="none" stroke="#1B1B22" stroke-width="1.800" stroke-linecap="round" opacity=".85"/>'
            . '<path d="M0 1Q3 6 0 8" fill="none" class="pp-skin-s-stroke" stroke-width="1.600" stroke-linecap="round"/>'
            . $glasses . $mouth . $front . '</g>';
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Limbs
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * An arm drawn as thick rounded strokes: sleeve from the shoulder to the elbow (and a bit beyond), skin from there to the wrist, a hand.
     * $sleeve: fraction of the forearm still covered by the sleeve (0 short sleeve, 1 long sleeve).
     */
    private static function arm(array $s, array $e, array $h, float $sleeve = 0.0, float $w1 = 16, float $w2 = 12.5, string $cls = 'pp-top'): string
    {
        $mx = $e[0] + ($h[0] - $e[0]) * $sleeve;
        $my = $e[1] + ($h[1] - $e[1]) * $sleeve;
        $out = '<path d="M' . $e[0] . ' ' . $e[1] . 'L' . $h[0] . ' ' . $h[1] . '" fill="none" class="pp-skin-stroke" stroke-width="' . $w2 . '" stroke-linecap="round"/>';
        $out .= '<path d="M' . $s[0] . ' ' . $s[1] . 'L' . $e[0] . ' ' . $e[1] . ($sleeve > 0 ? 'L' . round($mx, 1) . ' ' . round($my, 1) : '') . '" fill="none" class="' . $cls . '-stroke" stroke-width="' . $w1 . '" stroke-linecap="round" stroke-linejoin="round"/>';
        if ($sleeve > 0) {   // cuff
            $out .= '<circle class="pp-top-s" cx="' . round($mx, 1) . '" cy="' . round($my, 1) . '" r="' . ($w1 / 2 - 1) . '"/>';
        }
        return $out . '<circle class="pp-skin" cx="' . $h[0] . '" cy="' . $h[1] . '" r="' . ($w2 / 2 + 1.500) . '"/>';
    }

    private static function legs(float $stance = 0): string
    {
        $a = $stance;
        return '<path class="pp-bot" d="M-31-158C-33-110-30-60-' . (27 + $a) . '-12L' . (-5 - $a) . '-12C-5-60-2-110 2-158Z"/>'
            . '<path class="pp-bot" d="M-2-158C2-110 5-60 ' . (5 + $a) . '-12L' . (27 + $a) . '-12C30-60 33-110 31-158Z"/>'
            . '<path class="pp-bot-s" opacity=".55" d="M12-158C14-110 18-60 ' . (16 + $a) . '-12L' . (27 + $a) . '-12C30-60 33-110 31-158Z"/>'
            . '<path class="pp-bot-s" opacity=".45" d="M-31-158C-33-110-30-60-' . (27 + $a) . '-12L' . (-18 - $a) . '-12C-20-60-22-110-18-158Z"/>'
            . '<path class="pp-bot-s" d="M' . (-27 - $a) . '-18L' . (-5 - $a) . '-18L' . (-5 - $a) . '-12L' . (-27 - $a) . '-12Z"/><path class="pp-bot-s" d="M' . (5 + $a) . '-18L' . (27 + $a) . '-18L' . (27 + $a) . '-12L' . (5 + $a) . '-12Z"/>'
            . '<path d="M-14-96Q-6-92 2-96M12-90Q20-86 28-92" fill="none" class="pp-bot-s-stroke" stroke-width="2" stroke-linecap="round" opacity=".7"/>'
            . '<path class="pp-shoe" d="M' . (-28 - $a) . '-13Q' . (-28 - $a) . '-21 ' . (-14 - $a) . '-21L' . (-5 - $a) . '-12Q-2-9 0-4L0 0L' . (-37 - $a) . ' 0Q' . (-40 - $a) . '-9 ' . (-28 - $a) . '-13Z"/>'
            . '<path class="pp-shoe" d="M' . (28 + $a) . '-13Q' . (28 + $a) . '-21 ' . (14 + $a) . '-21L' . (5 + $a) . '-12Q2-9 0-4L0 0L' . (37 + $a) . ' 0Q' . (40 + $a) . '-9 ' . (28 + $a) . '-13Z"/>'
            . '<path d="M' . (-38 - $a) . '-3L-1-3M' . (38 + $a) . '-3L1-3" stroke="#000" stroke-opacity=".22" stroke-width="3" stroke-linecap="round" fill="none"/>';
    }

    private static function torso(): string
    {
        return '<path class="pp-top" d="M-37-258Q-37-272-22-275L-9-278L0-268L9-278L22-275Q37-272 37-258C40-222 30-196 35-166Q36-154 38-150Q0-141-38-150Q-36-154-35-166C-30-196-40-222-37-258Z"/>'
            . '<path class="pp-inner" d="M-9-278L0-268L9-278L6-150L-6-150Z"/>'
            . '<path class="pp-top-s" opacity=".55" d="M14-274Q37-272 37-258C40-222 30-196 35-166Q36-154 38-150Q26-147 14-146Q24-210 14-274Z"/>'
            . '<path class="pp-top-s" d="M-9-278L0-268L-6-250L-14-262Z"/><path class="pp-top-s" d="M9-278L0-268L6-250L14-262Z" opacity=".8"/>'
            . '<path d="M-22-204Q-10-198 2-200M20-188Q27-186 33-190M-30-172Q-18-168-8-170" fill="none" class="pp-top-s-stroke" stroke-width="2" stroke-linecap="round" opacity=".6"/>'
            . '<path class="pp-skin-s" d="M-7-280L-7-272L0-266L7-272L7-280Z"/>';
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Figures
    // -----------------------------------------------------------------------------------------------------------------

    /** A standing person. $pose: present | tablet | rest | cheer | wave | scope | phone. */
    public static function standing(string $who, string $pose = 'rest', float $tilt = 0): string
    {
        $sleeve = $who === 'idris' ? 0.0 : (in_array($who, ['marc', 'nora'], true) ? 0.55 : 1.0);
        $a = '';
        $front = '';
        switch ($pose) {
            case 'present':
                $a = self::arm([-33, -260], [-50, -204], [-30, -170], $sleeve) . self::arm([33, -260], [64, -240], [88, -282], $sleeve);
                $front = '<g transform="translate(-28 -176) rotate(-18)"><rect x="-17" y="-13" width="34" height="26" rx="4" fill="#20232D"/><rect x="-14.500" y="-10.500" width="29" height="21" rx="2" fill="url(#pp-screen)"/><rect x="-11" y="-6" width="14" height="3" rx="1.500" fill="#fff" opacity=".85"/><rect x="-11" y="0" width="22" height="3" rx="1.500" fill="#fff" opacity=".5"/></g>'
                    . '<circle class="pp-skin" cx="-30" cy="-170" r="7.500"/><path d="M88-282L98-300" fill="none" class="pp-skin-stroke" stroke-width="5" stroke-linecap="round"/>';
                break;
            case 'tablet':
                $a = self::arm([-33, -260], [-54, -208], [-24, -226], $sleeve) . self::arm([33, -260], [56, -212], [14, -204], $sleeve);
                $front = '<g transform="translate(2 -214) rotate(7)"><rect x="-20" y="-15" width="40" height="30" rx="4" fill="#20232D"/><rect x="-17.500" y="-12.500" width="35" height="25" rx="2" fill="url(#pp-screen)"/><rect x="-13" y="-8" width="16" height="3.200" rx="1.600" fill="#fff" opacity=".9"/><rect x="-13" y="-1.500" width="26" height="3.200" rx="1.600" fill="#fff" opacity=".5"/><rect x="-13" y="5" width="20" height="3.200" rx="1.600" fill="#fff" opacity=".5"/></g>'
                    . '<g transform="translate(-24 -226)"><path d="M-8-8L8-8L7 8Q0 11-7 8Z" fill="#F3EBDD"/><path d="M8-5Q15-4 14 2Q13 6 8 5" fill="none" stroke="#F3EBDD" stroke-width="2.600"/><path d="M-7-3L7-3" stroke="#B5482A" stroke-width="3"/></g>'
                    . '<circle class="pp-skin" cx="14" cy="-204" r="7"/>';
                break;
            case 'cheer':
                $a = self::arm([-33, -260], [-64, -296], [-80, -338], $sleeve) . self::arm([33, -260], [64, -296], [80, -338], $sleeve);
                break;
            case 'wave':
                $a = self::arm([-33, -260], [-46, -204], [-40, -160], $sleeve) . self::arm([33, -260], [60, -232], [92, -250], $sleeve);
                $front = '<g transform="translate(104 -258) rotate(-12)"><rect x="-26" y="-17" width="52" height="34" rx="4" fill="#FBF8F2" stroke="#DDD5C3" stroke-width="2"/><path d="M-26-17L0 4L26-17" fill="none" stroke="#DDD5C3" stroke-width="2" stroke-linejoin="round"/><circle cx="0" cy="3" r="7" fill="#B5482A"/><path d="M-3.500 3L-1 5.500L4-.5" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></g>'
                    . '<circle class="pp-skin" cx="92" cy="-250" r="7.500"/>';
                break;
            case 'scope':
                $a = self::arm([-33, -260], [-50, -214], [-14, -226], $sleeve) . self::arm([33, -260], [58, -222], [52, -246], $sleeve);
                break;
            case 'phone':
                $a = self::arm([-33, -260], [-46, -204], [-38, -162], $sleeve) . self::arm([33, -260], [58, -226], [26, -266], $sleeve);
                $front = '<rect x="20" y="-290" width="11" height="20" rx="2.500" transform="rotate(14 25 -280)" fill="#20232D"/>';
                break;
            default: // rest
                $a = self::arm([-33, -260], [-46, -204], [-38, -158], $sleeve) . self::arm([33, -260], [46, -204], [38, -158], $sleeve);
        }
        return '<g class="pp" style="' . self::vars($who) . '">'
            . '<ellipse cx="0" cy="2" rx="46" ry="6" fill="#000" opacity=".16"/>'
            . self::legs() . self::torso() . $a . $front
            . '<g transform="translate(0 -304)">' . self::head($who, $tilt) . '</g></g>';
    }

    /**
     * A person at a desk, seen from the front: the table top is y = 0, the figure rises above it (head centre about y = -170). Hands rest forward.
     * Draw the desk after the figure so it hides the lower body.
     */
    public static function seated(string $who, string $hands = 'type'): string
    {
        $l = self::look($who);
        $sleeve = $who === 'idris' ? 0.0 : (in_array($who, ['marc', 'nora'], true) ? 0.55 : 1.0);
        $arms = $hands === 'chin'
            ? self::arm([-33, -118], [-52, -62], [-30, -22], $sleeve) . self::arm([33, -118], [50, -66], [14, -92], $sleeve)
            : self::arm([-33, -118], [-52, -60], [-18, -14], $sleeve) . self::arm([33, -118], [52, -60], [18, -14], $sleeve);
        return '<g class="pp" style="' . self::vars($who) . '">'
            . '<g transform="translate(0 142)">' . self::torso() . '</g>'
            . $arms
            . '<g transform="translate(0 -162)">' . self::head($who, $hands === 'chin' ? 5.0 : -2.0) . '</g></g>';
    }

    /** Head and shoulders: a bust for small scenes. The neck base is at y = 0, the head centre at y = -50. $hand: none | doc | up | think. */
    public static function bust(string $who, string $hand = 'none', float $tilt = 0): string
    {
        $sleeve = $who === 'idris' ? 0.0 : (in_array($who, ['marc', 'nora'], true) ? 0.55 : 1.0);
        $body = '<path class="pp-top" d="M-46 84Q-48 22-30 8L-9 1L0 12L9 1L30 8Q48 22 46 84Z"/><path class="pp-inner" d="M-9 1L0 12L9 1L7 84L-7 84Z"/>'
              . '<path class="pp-top-s" opacity=".55" d="M18 6Q48 22 46 84L26 84Q34 40 18 6Z"/><path class="pp-skin-s" d="M-7-4L-7 4L0 10L7 4L7-4Z"/>';
        $extra = '';
        if ($hand === 'doc') {
            $extra = self::arm([-30, 40], [-48, 78], [-16, 92], $sleeve) . '<g transform="translate(-6 78) rotate(-8)"><rect x="-22" y="-26" width="44" height="54" rx="4" fill="#FBF8F2" stroke="#DDD5C3" stroke-width="2"/><rect x="-14" y="-17" width="22" height="4" rx="2" fill="#B5482A"/><rect x="-14" y="-8" width="28" height="3.500" rx="1.700" fill="#DDD5C3"/><rect x="-14" y="0" width="22" height="3.500" rx="1.700" fill="#DDD5C3"/><circle cx="8" cy="17" r="8" fill="#2F5740"/><path d="M4 17L7 20L12 14" fill="none" stroke="#fff" stroke-width="2.200" stroke-linecap="round" stroke-linejoin="round"/></g><circle class="pp-skin" cx="-16" cy="92" r="7"/>';
        } elseif ($hand === 'up') {
            $extra = self::arm([30, 40], [58, 28], [64, -6], $sleeve) . '<path d="M60-14L56-30" fill="none" class="pp-skin-stroke" stroke-width="6.500" stroke-linecap="round"/>';
        }
        $after = '';
        if ($hand === 'think') {   // a hand under the chin, drawn over the face
            $after = self::arm([30, 40], [38, 86], [12, -14], $sleeve);
        }
        return '<g class="pp" style="' . self::vars($who) . '">' . $body . $extra . '<g transform="translate(0 -50)">' . self::head($who, $tilt) . '</g>' . $after . '</g>';
    }

    /** Defs every scene that uses the kit needs once: the colour classes (also in CSS, see careers.css) are in the stylesheet, only gradients here. */
    public static function defs(): string
    {
        return '<linearGradient id="pp-screen" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4C6FFF"/><stop offset="1" stop-color="#18C29C"/></linearGradient>';
    }
}
