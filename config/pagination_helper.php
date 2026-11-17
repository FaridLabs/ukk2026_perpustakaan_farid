<?php
/**
 * ============================================================================
 * HELPER PAGINATION - PINJAM.IN
 * ============================================================================
 * Menyediakan fungsi perhitungan offset/limit SQL dan generator navigasi
 * pagination yang responsif, terintegrasi, dan mempertahankan query string
 * (filter pencarian, kategori, tanggal, dll).
 */

if (!function_exists('hitungPagination')) {
    /**
     * Menghitung parameter limit, offset, dan data pagination.
     *
     * @param int $totalData   Total baris data hasil COUNT query
     * @param int $perPage     Jumlah baris per halaman (default 10)
     * @param int $currentPage Halaman yang sedang aktif (dari $_GET['page'])
     * @return array
     */
    function hitungPagination(int $totalData, int $perPage = 10, int $currentPage = 1): array {
        $perPage   = max(1, $perPage);
        $totalData = max(0, $totalData);
        $totalPages = $totalData > 0 ? (int) ceil($totalData / $perPage) : 1;

        $currentPage = max(1, $currentPage);
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $offset = ($currentPage - 1) * $perPage;
        $from   = $totalData > 0 ? $offset + 1 : 0;
        $to     = min($offset + $perPage, $totalData);

        return [
            'total_data'   => $totalData,
            'per_page'     => $perPage,
            'current_page' => $currentPage,
            'total_pages'  => $totalPages,
            'offset'       => $offset,
            'limit'        => $perPage,
            'from'         => $from,
            'to'           => $to,
        ];
    }
}

if (!function_exists('renderPagination')) {
    /**
     * Menghasilkan markup HTML navigasi pagination dengan pelestarian query string.
     *
     * @param array       $pg         Array hasil hitungPagination()
     * @param string      $extraClass Class CSS tambahan untuk pembungkus (.pg-wrap)
     * @param array|null  $params     Array parameter query kustom (default: $_GET)
     * @return string     HTML string
     */
    function renderPagination(array $pg, string $extraClass = '', ?array $params = null): string {
        $totalData  = (int) ($pg['total_data'] ?? 0);
        $totalPages = (int) ($pg['total_pages'] ?? 1);
        $curr       = (int) ($pg['current_page'] ?? 1);
        $from       = (int) ($pg['from'] ?? 0);
        $to         = (int) ($pg['to'] ?? 0);

        if ($totalData === 0) {
            return '';
        }

        if ($params === null) {
            $params = $_GET;
        }

        $buildUrl = function(int $pageNum) use ($params): string {
            $q = array_merge($params, ['page' => $pageNum]);
            return '?' . http_build_query($q);
        };

        $html = '<div class="pg-wrap ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="pg-info">';
        $html .= 'Menampilkan <b>' . $from . '</b>–<b>' . $to . '</b> dari <b>' . $totalData . '</b> data';
        if ($totalPages > 1) {
            $html .= ' <span style="opacity:0.65;font-size:12px;">(Hal. ' . $curr . ' / ' . $totalPages . ')</span>';
        }
        $html .= '</div>';

        if ($totalPages > 1) {
            $html .= '<nav class="pg-nav" aria-label="Navigasi Halaman">';

            // Tombol Previous
            if ($curr > 1) {
                $html .= '<a href="' . htmlspecialchars($buildUrl($curr - 1), ENT_QUOTES, 'UTF-8') . '" class="pg-btn" aria-label="Halaman Sebelumnya" title="Halaman Sebelumnya">&lsaquo;</a>';
            } else {
                $html .= '<span class="pg-btn is-disabled" aria-hidden="true">&lsaquo;</span>';
            }

            // Hitung nomor halaman dengan ellipsis
            $pages = [];
            if ($totalPages <= 7) {
                for ($p = 1; $p <= $totalPages; $p++) {
                    $pages[] = $p;
                }
            } else {
                $pages[] = 1;
                if ($curr <= 4) {
                    for ($p = 2; $p <= 5; $p++) {
                        $pages[] = $p;
                    }
                    $pages[] = '...';
                    $pages[] = $totalPages;
                } elseif ($curr >= $totalPages - 3) {
                    $pages[] = '...';
                    for ($p = $totalPages - 4; $p <= $totalPages; $p++) {
                        $pages[] = $p;
                    }
                } else {
                    $pages[] = '...';
                    for ($p = $curr - 1; $p <= $curr + 1; $p++) {
                        $pages[] = $p;
                    }
                    $pages[] = '...';
                    $pages[] = $totalPages;
                }
            }

            // Render nomor-nomor halaman
            foreach ($pages as $p) {
                if ($p === '...') {
                    $html .= '<span class="pg-dots">&hellip;</span>';
                } elseif ($p === $curr) {
                    $html .= '<span class="pg-btn is-active" aria-current="page">' . $p . '</span>';
                } else {
                    $html .= '<a href="' . htmlspecialchars($buildUrl((int)$p), ENT_QUOTES, 'UTF-8') . '" class="pg-btn" aria-label="Halaman ' . $p . '">' . $p . '</a>';
                }
            }

            // Tombol Next
            if ($curr < $totalPages) {
                $html .= '<a href="' . htmlspecialchars($buildUrl($curr + 1), ENT_QUOTES, 'UTF-8') . '" class="pg-btn" aria-label="Halaman Berikutnya" title="Halaman Berikutnya">&rsaquo;</a>';
            } else {
                $html .= '<span class="pg-btn is-disabled" aria-hidden="true">&rsaquo;</span>';
            }

            $html .= '</nav>';
        }

        $html .= '</div>';
        return $html;
    }
}
