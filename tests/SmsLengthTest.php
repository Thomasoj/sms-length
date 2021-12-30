<?php

declare(strict_types=1);

namespace Phlib\SmsLength;

use Phlib\SmsLength\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SmsLengthTest extends TestCase
{
    /**
     * @see https://en.wikipedia.org/wiki/GSM_03.38
     * @var string[] Printable characters in GSM 03.38 7-bit default alphabet
     *               0x1B deliberately excluded as it's used to escape to extension table
     */
    private const GSM0338_BASIC =
        '@'  . 'Δ' . ' ' . '0' . '¡' . 'P' . '¿' . 'p' .
        '£'  . '_' . '!' . '1' . 'A' . 'Q' . 'a' . 'q' .
        '$'  . 'Φ' . '"' . '2' . 'B' . 'R' . 'b' . 'r' .
        '¥'  . 'Γ' . '#' . '3' . 'C' . 'S' . 'c' . 's' .
        'è'  . 'Λ' . '¤' . '4' . 'D' . 'T' . 'd' . 't' .
        'é'  . 'Ω' . '%' . '5' . 'E' . 'U' . 'e' . 'u' .
        'ù'  . 'Π' . '&' . '6' . 'F' . 'V' . 'f' . 'v' .
        'ì'  . 'Ψ' . "'" . '7' . 'G' . 'W' . 'g' . 'w' .
        'ò'  . 'Σ' . '(' . '8' . 'H' . 'X' . 'h' . 'x' .
        'Ç'  . 'Θ' . ')' . '9' . 'I' . 'Y' . 'i' . 'y' .
        "\n" . 'Ξ' . '*' . ':' . 'J' . 'Z' . 'j' . 'z' .
        'Ø'        . '+' . ';' . 'K' . 'Ä' . 'k' . 'ä' .
        'ø'  . 'Æ' . ',' . '<' . 'L' . 'Ö' . 'l' . 'ö' .
        "\r" . 'æ' . '-' . '=' . 'M' . 'Ñ' . 'm' . 'ñ' .
        'Å'  . 'ß' . '.' . '>' . 'N' . 'Ü' . 'n' . 'ü' .
        'å'  . 'É' . '/' . '?' . 'O' . '§' . 'o' . 'à'
    ;

    /**
     * @see https://en.wikipedia.org/wiki/GSM_03.38
     * @var string[] Printable characters in GSM 03.38 7-bit extension table
     */
    private const GSM0338_EXTENDED = '|^€{}[~]\\';

    /**
     * @dataProvider providerSize
     */
    public function testSize(
        string $content,
        string $encoding,
        int $characters,
        int $messageCount,
        int $upperBreak
    ): void {
        $size = new SmsLength($content);

        static::assertSame($encoding, $size->getEncoding());
        static::assertSame($characters, $size->getSize());
        static::assertSame($messageCount, $size->getMessageCount());
        static::assertSame($upperBreak, $size->getUpperBreakpoint());

        static::assertTrue($size->validate());
    }

    public function providerSize(): array
    {
        return [
            'gsm-basic' => [self::GSM0338_BASIC, '7-bit', 127, 1, 160],
            'gsm-extended' => [self::GSM0338_EXTENDED, '7-bit', 18, 1, 160],
            'simple-basic' => ['simple msg', '7-bit', 10, 1, 160],
            'simple-extended' => ['simple msg plus € extended char', '7-bit', 32, 1, 160],

            // http://www.fileformat.info/info/unicode/char/2022/index.htm
            'utf16-1' => ['simple msg plus • 1 UTF-16 code unit char', 'ucs-2', 41, 1, 70],

            // http://www.fileformat.info/info/unicode/char/1f4f1/index.htm
            'utf16-2' => ["simple msg plus \xf0\x9f\x93\xb1 2 UTF-16 code unit char", 'ucs-2', 42, 1, 70],

            // long 7-bit
            'long-gsm-simple' => [str_repeat('simple msg', 50), '7-bit', 500, 4, 612],
            'long-gsm-exact' => [str_repeat('exact max', 153), '7-bit', 1377, 9, 1377],

            // long 7-bit extended
            'long-gsm-ex-1' => [str_repeat(self::GSM0338_EXTENDED, 40), '7-bit', 724, 5, 765],
            'long-gsm-ex-2' => [str_repeat(self::GSM0338_EXTENDED, 76), '7-bit', 1376, 9, 1377],

            // long UCS-2
            'long-ucs-1' => [str_repeat('simple msg plus •', 20), 'ucs-2', 340, 6, 402],
            'long-ucs-2' => [str_repeat("simple msg plus \xf0\x9f\x93\xb1", 20), 'ucs-2', 360, 6, 402],
            'long-ucs-exact' => [str_repeat('exact•max', 67), 'ucs-2', 603, 9, 603],

            // empty
            'empty' => ['', '7-bit', 0, 1, 160],
        ];
    }

    /**
     * @dataProvider providerMessageParts
     */
    public function testDivideMessage(array $parts, int $characters): void {
        $message = implode('', $parts);

        $size = new SmsLength($message);

        static::assertSame($parts, $size->getMessageParts());
        static::assertCount(count($parts), $size->getMessageParts());
        static::assertSame($characters, $size->getSize());
    }

    public function providerMessageParts(): array
    {
       return [
           // 7-bit
           'one-part-basic' => [[
               1 => $this->getChars(self::GSM0338_BASIC, 160)
           ], 160],
           'two-parts-basic' => [[
               1 => $this->getChars(self::GSM0338_BASIC, 153),
               2 => $this->getChars(self::GSM0338_BASIC, 153)
           ], 306],

           // 7-bit extended
           'one-part-extended' => [[
               1 => $this->getChars(self::GSM0338_EXTENDED, 80)
           ], 160],
           'two-part-extended' => [[
               1 => $this->getChars(self::GSM0338_BASIC, 153),
               2 => $this->getChars(self::GSM0338_EXTENDED, 76)
           ], 305],

           // ucs-2
           'one-part-ucs' => [[
               1 => $this->getChars('simple msg plus •', 70)
           ], 70],
           'two-part-ucs' => [[
               1 => $this->getChars('simple msg plus •', 67),
               2 => $this->getChars('simple msg plus •', 67),
           ], 134],
           'one-part-ucs-double' => [[
               1 => $this->getChars('simple msg plus ', 68) . "\xf0\x9f\x93\xb1",
           ], 70],
           'two-part-ucs-double' => [[
               1 => $this->getChars('simple msg plus ', 65) . "\xf0\x9f\x93\xb1",
               2 => $this->getChars('simple msg plus ', 65) . "\xf0\x9f\x93\xb1",
           ], 134],
        ];
    }

    /**
     * Characters from extension table (7bit) or doubled character (ucs2) can not been split to two parts of message.
     * To prevent this is character moved to next part of message, so first part has one character less.
     *
     * @dataProvider providerPreventSplitDoubleCharacter
     */
    public function testPreventSplitDoubleCharacter(array $parts, int $firstMessageLength): void
    {
        $message = implode('', $parts);
        $characters = mb_strlen($message, 'UTF-8');
        $messageCount = count($parts);
        $size = new SmsLength($message);

        $firstMessagePart = $size->getMessageParts()[1];

        self::assertSame($messageCount, $size->getMessageCount());
        self::assertSame($parts, $size->getMessageParts());
        // original message length + escape (or doubled) character + padding at end of the first part
        self::assertSame($characters + 2, $size->getSize());
        self::assertSame($firstMessageLength, mb_strlen($firstMessagePart, 'UTF-8'));
    }

    public function providerPreventSplitDoubleCharacter(): array
    {
        return [
            '7bit' => [
                [
                    // 153 - 1 padding = 152
                    1 => $this->getChars(self::GSM0338_BASIC, 152),
                    // 2 (escaped char) + 151 = 153
                    2 => $this->getChars(self::GSM0338_EXTENDED, 1) . $this->getChars(self::GSM0338_BASIC, 151),
                    // check if 3. part of message is created
                    3 => $this->getChars(self::GSM0338_BASIC, 1)
                ],
                152 // Escaped character is moved to next part, so first part has 153 - 1 padding = 152 chars
            ],
            'ucs2' => [
                [
                    // 67 - 1 padding = 66
                    1 => $this->getChars(self::GSM0338_BASIC, 66),
                    // 2 (doubled char) + 65 = 67
                    2 => "\xf0\x9f\x93\xb1" . $this->getChars(self::GSM0338_BASIC, 65),
                    // check, if 3. part of message is created
                    3 => $this->getChars(self::GSM0338_BASIC, 1)
                ],
                66 // Doubled character is moved to next part, so first part of message has 67 - 1 padding = 66 chars
            ]
        ];
    }

    /**
     * @dataProvider providerTooLarge
     * @medium Expect tests to take >1 but <10
     */
    public function testTooLarge(
        string $content,
        string $encoding,
        int $characters,
        int $messageCount,
        int $upperBreak
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message count cannot exceed 255');

        $size = new SmsLength($content);

        static::assertSame($encoding, $size->getEncoding());
        static::assertSame($characters, $size->getSize());
        static::assertSame($messageCount, $size->getMessageCount());
        static::assertSame($upperBreak, $size->getUpperBreakpoint());

        // Trigger exception
        $size->validate();
    }

    public function providerTooLarge(): array
    {
        // 7-bit max is 39015 (255 * 153)
        // ucs-2 max is 17085 (255 * 67)
        return [
            // long 7-bit, 10 * 3902 = 39020
            'basic' => [str_repeat('simple msg', 3902), '7-bit', 39020, 256, 39168],

            // long 7-bit extended, 18 * 2154 + 255 paddings = 39027
            'extended' => [str_repeat(self::GSM0338_EXTENDED, 2154), '7-bit', 39027, 256, 39168],

            // long UCS-2 single, 17 * 1006 = 17102
            // long UCS-2 double, 18 * 950 + 35 paddings = 17100
            'ucs-1' => [str_repeat('simple msg plus •', 1006), 'ucs-2', 17102, 256, 17152],
            'ucs-2' => [str_repeat("simple msg plus \xf0\x9f\x93\xb1", 950), 'ucs-2', 17135, 256, 17152],
        ];
    }

    /**
     * Trims or extends the character set to the exact length
     */
    private function getChars(string $charSet, int $size): string
    {
        $chars = $charSet;
        while (mb_strlen($chars, 'UTF-8') < $size) {
            $chars .= $charSet;
        }

        return mb_substr($chars, 0, $size);
    }
}
