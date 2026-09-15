-- Tabel untuk menyimpan transaksi/invoice dari AiYO Bills Invoice
-- Sesuai skema pada slide "Tabel transaksi"

CREATE TABLE `transaksi` (
  `referenceId` varchar(25) NOT NULL,
  `userName` varchar(50) NOT NULL,
  `userEmail` varchar(40) NOT NULL,
  `userPhone` varchar(20) NOT NULL,
  `remarks` text NOT NULL,
  `payAmount` bigint(20) UNSIGNED NOT NULL,
  `items` text NOT NULL,
  `invoiceId` varchar(25) NOT NULL,
  `status` varchar(10) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`invoiceId`);

COMMIT;
