<?php

/**
 * Minimal, dependency-free QR code encoder (byte mode, versions 1–40).
 *
 * Follows ISO/IEC 18004. Structure modelled on Project Nayuki's reference
 * QR Code generator (MIT licence). Produces a boolean module matrix and can
 * emit it as a PNG data URI without needing the GD extension.
 */
class CRM_KasUser_QrCode {

  private const ECC_PER_BLOCK = [
    'L' => [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    'M' => [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
    'Q' => [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    'H' => [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
  ];

  private const NUM_BLOCKS = [
    'L' => [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
    'M' => [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
    'Q' => [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
    'H' => [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
  ];

  private const FORMAT_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

  /** @var int */
  private $version;
  /** @var string */
  private $ecl;
  /** @var int */
  private $size;
  /** @var bool[][] */
  private $modules = [];
  /** @var bool[][] */
  private $isFunction = [];

  /**
   * Encode text (UTF-8 bytes) as a QR matrix.
   *
   * @return bool[][] rows of modules, TRUE = dark
   */
  public static function encode(string $text, string $ecl = 'M'): array {
    if (!isset(self::FORMAT_BITS[$ecl])) {
      throw new InvalidArgumentException("Unknown error-correction level '$ecl'");
    }
    $data = $text === '' ? [] : array_values(unpack('C*', $text));

    $version = 0;
    $ccBits = 8;
    for ($v = 1; $v <= 40; $v++) {
      $ccBits = $v <= 9 ? 8 : 16;
      if (4 + $ccBits + 8 * count($data) <= self::numDataCodewords($v, $ecl) * 8) {
        $version = $v;
        break;
      }
    }
    if (!$version) {
      throw new InvalidArgumentException('Text is too long to fit in a QR code');
    }

    $capacity = self::numDataCodewords($version, $ecl) * 8;
    $bits = [];
    self::appendBits($bits, 0x4, 4);
    self::appendBits($bits, count($data), $ccBits);
    foreach ($data as $b) {
      self::appendBits($bits, $b, 8);
    }
    self::appendBits($bits, 0, min(4, $capacity - count($bits)));
    self::appendBits($bits, 0, (8 - count($bits) % 8) % 8);
    for ($pad = 0xEC; count($bits) < $capacity; $pad ^= 0xEC ^ 0x11) {
      self::appendBits($bits, $pad, 8);
    }

    $codewords = array_fill(0, count($bits) >> 3, 0);
    foreach ($bits as $i => $bit) {
      $codewords[$i >> 3] |= $bit << (7 - ($i & 7));
    }

    $qr = new self($version, $ecl);
    return $qr->build($codewords);
  }

  /**
   * Render a matrix as a PNG data URI (8-bit greyscale, no GD needed).
   */
  public static function toPngDataUri(array $matrix, int $scale = 10, int $quiet = 4): string {
    $n = count($matrix);
    $px = ($n + 2 * $quiet) * $scale;
    $raw = '';
    $blankRow = "\x00" . str_repeat("\xFF", $px);
    for ($y = -$quiet; $y < $n + $quiet; $y++) {
      if ($y < 0 || $y >= $n) {
        $row = $blankRow;
      }
      else {
        $line = str_repeat("\xFF", $quiet * $scale);
        foreach ($matrix[$y] as $dark) {
          $line .= str_repeat($dark ? "\x00" : "\xFF", $scale);
        }
        $line .= str_repeat("\xFF", $quiet * $scale);
        $row = "\x00" . $line;
      }
      $raw .= str_repeat($row, $scale);
    }
    $png = "\x89PNG\r\n\x1a\n"
      . self::pngChunk('IHDR', pack('NNCCCCC', $px, $px, 8, 0, 0, 0, 0))
      . self::pngChunk('IDAT', gzcompress($raw, 9))
      . self::pngChunk('IEND', '');
    return 'data:image/png;base64,' . base64_encode($png);
  }

  private static function pngChunk(string $type, string $data): string {
    return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
  }

  private function __construct(int $version, string $ecl) {
    $this->version = $version;
    $this->ecl = $ecl;
    $this->size = $version * 4 + 17;
    $row = array_fill(0, $this->size, FALSE);
    $this->modules = array_fill(0, $this->size, $row);
    $this->isFunction = array_fill(0, $this->size, $row);
  }

  private function build(array $codewords): array {
    $this->drawFunctionPatterns();
    $this->drawCodewords($this->addEccAndInterleave($codewords));

    $best = 0;
    $minPenalty = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
      $this->applyMask($mask);
      $this->drawFormatBits($mask);
      $penalty = $this->penalty();
      if ($penalty < $minPenalty) {
        $minPenalty = $penalty;
        $best = $mask;
      }
      // XOR again to undo.
      $this->applyMask($mask);
    }
    $this->applyMask($best);
    $this->drawFormatBits($best);
    return $this->modules;
  }

  // ---- Function patterns -------------------------------------------------

  private function drawFunctionPatterns(): void {
    for ($i = 0; $i < $this->size; $i++) {
      $this->setFunction(6, $i, $i % 2 === 0);
      $this->setFunction($i, 6, $i % 2 === 0);
    }
    $this->drawFinder(3, 3);
    $this->drawFinder($this->size - 4, 3);
    $this->drawFinder(3, $this->size - 4);

    $pos = $this->alignmentPositions();
    $n = count($pos);
    for ($i = 0; $i < $n; $i++) {
      for ($j = 0; $j < $n; $j++) {
        if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
          continue;
        }
        $this->drawAlignment($pos[$i], $pos[$j]);
      }
    }
    $this->drawFormatBits(0);
    $this->drawVersion();
  }

  private function drawFinder(int $x, int $y): void {
    for ($dy = -4; $dy <= 4; $dy++) {
      for ($dx = -4; $dx <= 4; $dx++) {
        $dist = max(abs($dx), abs($dy));
        $xx = $x + $dx;
        $yy = $y + $dy;
        if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
          $this->setFunction($xx, $yy, $dist !== 2 && $dist !== 4);
        }
      }
    }
  }

  private function drawAlignment(int $x, int $y): void {
    for ($dy = -2; $dy <= 2; $dy++) {
      for ($dx = -2; $dx <= 2; $dx++) {
        $this->setFunction($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
      }
    }
  }

  private function alignmentPositions(): array {
    if ($this->version === 1) {
      return [];
    }
    $num = intdiv($this->version, 7) + 2;
    $step = intdiv($this->version * 8 + $num * 3 + 5, $num * 4 - 4) * 2;
    $result = [6];
    for ($pos = $this->size - 7; count($result) < $num; $pos -= $step) {
      array_splice($result, 1, 0, [$pos]);
    }
    return $result;
  }

  private function drawFormatBits(int $mask): void {
    $data = (self::FORMAT_BITS[$this->ecl] << 3) | $mask;
    $rem = $data;
    for ($i = 0; $i < 10; $i++) {
      $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
    }
    $bits = (($data << 10) | $rem) ^ 0x5412;

    for ($i = 0; $i <= 5; $i++) {
      $this->setFunction(8, $i, self::bit($bits, $i));
    }
    $this->setFunction(8, 7, self::bit($bits, 6));
    $this->setFunction(8, 8, self::bit($bits, 7));
    $this->setFunction(7, 8, self::bit($bits, 8));
    for ($i = 9; $i < 15; $i++) {
      $this->setFunction(14 - $i, 8, self::bit($bits, $i));
    }

    for ($i = 0; $i < 8; $i++) {
      $this->setFunction($this->size - 1 - $i, 8, self::bit($bits, $i));
    }
    for ($i = 8; $i < 15; $i++) {
      $this->setFunction(8, $this->size - 15 + $i, self::bit($bits, $i));
    }
    // Always-dark module.
    $this->setFunction(8, $this->size - 8, TRUE);
  }

  private function drawVersion(): void {
    if ($this->version < 7) {
      return;
    }
    $rem = $this->version;
    for ($i = 0; $i < 12; $i++) {
      $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
    }
    $bits = ($this->version << 12) | $rem;
    for ($i = 0; $i < 18; $i++) {
      $bit = self::bit($bits, $i);
      $a = $this->size - 11 + $i % 3;
      $b = intdiv($i, 3);
      $this->setFunction($a, $b, $bit);
      $this->setFunction($b, $a, $bit);
    }
  }

  private function setFunction(int $x, int $y, bool $dark): void {
    $this->modules[$y][$x] = $dark;
    $this->isFunction[$y][$x] = TRUE;
  }

  // ---- Data and error correction ----------------------------------------

  private function addEccAndInterleave(array $data): array {
    $numBlocks = self::NUM_BLOCKS[$this->ecl][$this->version];
    $blockEccLen = self::ECC_PER_BLOCK[$this->ecl][$this->version];
    $rawCodewords = intdiv(self::numRawDataModules($this->version), 8);
    $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
    $shortBlockLen = intdiv($rawCodewords, $numBlocks);

    $divisor = self::rsDivisor($blockEccLen);
    $blocks = [];
    for ($i = 0, $k = 0; $i < $numBlocks; $i++) {
      $len = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
      $dat = array_slice($data, $k, $len);
      $k += $len;
      $ecc = self::rsRemainder($dat, $divisor);
      if ($i < $numShortBlocks) {
        $dat[] = 0;
      }
      $blocks[] = array_merge($dat, $ecc);
    }

    $result = [];
    $blockLen = count($blocks[0]);
    for ($i = 0; $i < $blockLen; $i++) {
      foreach ($blocks as $j => $block) {
        if ($i !== $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) {
          $result[] = $block[$i];
        }
      }
    }
    return $result;
  }

  private function drawCodewords(array $data): void {
    $total = count($data) * 8;
    $i = 0;
    for ($right = $this->size - 1; $right >= 1; $right -= 2) {
      if ($right === 6) {
        $right = 5;
      }
      for ($vert = 0; $vert < $this->size; $vert++) {
        for ($j = 0; $j < 2; $j++) {
          $x = $right - $j;
          $upward = (($right + 1) & 2) === 0;
          $y = $upward ? $this->size - 1 - $vert : $vert;
          if (!$this->isFunction[$y][$x] && $i < $total) {
            $this->modules[$y][$x] = self::bit($data[$i >> 3], 7 - ($i & 7));
            $i++;
          }
        }
      }
    }
  }

  private function applyMask(int $mask): void {
    for ($y = 0; $y < $this->size; $y++) {
      for ($x = 0; $x < $this->size; $x++) {
        switch ($mask) {
          case 0: $invert = ($x + $y) % 2 === 0; break;
          case 1: $invert = $y % 2 === 0; break;
          case 2: $invert = $x % 3 === 0; break;
          case 3: $invert = ($x + $y) % 3 === 0; break;
          case 4: $invert = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
          case 5: $invert = ($x * $y % 2 + $x * $y % 3) === 0; break;
          case 6: $invert = ($x * $y % 2 + $x * $y % 3) % 2 === 0; break;
          default: $invert = (($x + $y) % 2 + $x * $y % 3) % 2 === 0; break;
        }
        if ($invert && !$this->isFunction[$y][$x]) {
          $this->modules[$y][$x] = !$this->modules[$y][$x];
        }
      }
    }
  }

  /**
   * Penalty score used to choose a mask (rules 1, 2 and 4; any mask is valid).
   */
  private function penalty(): int {
    $n = $this->size;
    $result = 0;
    $dark = 0;
    for ($a = 0; $a < $n; $a++) {
      $runRow = 1;
      $runCol = 1;
      for ($b = 0; $b < $n; $b++) {
        if ($this->modules[$a][$b]) {
          $dark++;
        }
        if ($b > 0) {
          if ($this->modules[$a][$b] === $this->modules[$a][$b - 1]) {
            $runRow++;
          }
          else {
            $result += $runRow >= 5 ? $runRow - 2 : 0;
            $runRow = 1;
          }
          if ($this->modules[$b][$a] === $this->modules[$b - 1][$a]) {
            $runCol++;
          }
          else {
            $result += $runCol >= 5 ? $runCol - 2 : 0;
            $runCol = 1;
          }
        }
        if ($a > 0 && $b > 0) {
          $c = $this->modules[$a][$b];
          if ($c === $this->modules[$a - 1][$b] && $c === $this->modules[$a][$b - 1] && $c === $this->modules[$a - 1][$b - 1]) {
            $result += 3;
          }
        }
      }
      $result += $runRow >= 5 ? $runRow - 2 : 0;
      $result += $runCol >= 5 ? $runCol - 2 : 0;
    }
    $total = $n * $n;
    $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
    return $result + max(0, $k) * 10;
  }

  // ---- Helpers ------------------------------------------------------------

  private static function numRawDataModules(int $ver): int {
    $result = (16 * $ver + 128) * $ver + 64;
    if ($ver >= 2) {
      $numAlign = intdiv($ver, 7) + 2;
      $result -= (25 * $numAlign - 10) * $numAlign - 55;
      if ($ver >= 7) {
        $result -= 36;
      }
    }
    return $result;
  }

  private static function numDataCodewords(int $ver, string $ecl): int {
    return intdiv(self::numRawDataModules($ver), 8)
      - self::ECC_PER_BLOCK[$ecl][$ver] * self::NUM_BLOCKS[$ecl][$ver];
  }

  private static function rsDivisor(int $degree): array {
    $result = array_fill(0, $degree, 0);
    $result[$degree - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
      for ($j = 0; $j < $degree; $j++) {
        $result[$j] = self::gfMultiply($result[$j], $root);
        if ($j + 1 < $degree) {
          $result[$j] ^= $result[$j + 1];
        }
      }
      $root = self::gfMultiply($root, 0x02);
    }
    return $result;
  }

  private static function rsRemainder(array $data, array $divisor): array {
    $result = array_fill(0, count($divisor), 0);
    foreach ($data as $b) {
      $factor = $b ^ array_shift($result);
      $result[] = 0;
      foreach ($divisor as $i => $coef) {
        $result[$i] ^= self::gfMultiply($coef, $factor);
      }
    }
    return $result;
  }

  private static function gfMultiply(int $x, int $y): int {
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
      $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
      $z ^= (($y >> $i) & 1) * $x;
    }
    return $z;
  }

  private static function appendBits(array &$bits, int $value, int $length): void {
    for ($i = $length - 1; $i >= 0; $i--) {
      $bits[] = ($value >> $i) & 1;
    }
  }

  private static function bit(int $x, int $i): bool {
    return (($x >> $i) & 1) !== 0;
  }

}
