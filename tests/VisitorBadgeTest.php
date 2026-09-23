<?php

use Talal\LabelPrinter\VisitorBadge;

class VisitorBadgeTest extends PHPUnit_Framework_TestCase
{
    public function testRenderWithoutPhoto()
    {
        $badge = new VisitorBadge(
            696,
            590,
            [
                'visitor_name' => 'John Doe',
                'company_name' => 'Example Ltd',
                'validity_date' => '2026-09-08',
                'host_name' => 'Jane Smith',
                'logo' => __DIR__ . '/fixtures/institution-logo.png'
            ]
        );

        $image = $badge->render();

        $this->assertTrue(is_resource($image) || is_object($image));
        $this->assertEquals(696, imagesx($image));
        $this->assertEquals(590, imagesy($image));

        imagedestroy($image);
    }

    public function testRenderWithPhoto()
    {
        $photoPath = __DIR__ . '/fixtures/visitor-photo.png';

        $badge = new VisitorBadge(
            696,
            590,
            [
                'visitor_name' => 'Stuart Burgess',
                'company_name' => 'A&D Buildings Ltd',
                'validity_date' => '09 Sept 2026',
                'host_name' => 'John Smith',
                'visitor_photo' => $photoPath,
                'logo' => __DIR__ . '/fixtures/institution-logo.png'
            ]
        );

        $image = $badge->render();

        $this->assertTrue(is_resource($image) || is_object($image));
        $this->assertEquals(696, imagesx($image));
        $this->assertEquals(590, imagesy($image));

        imagedestroy($image);
    }

    public function testRequiredFields()
    {
        $this->setExpectedException('InvalidArgumentException');

        new VisitorBadge(
            696,
            590,
            [
                'visitor_name' => 'John Doe',
                'company_name' => 'Example Ltd',
                'validity_date' => '2026-09-08'
            ]
        );
    }

    public function testPrintThroughEscp()
    {
        $stream = fopen('php://temp', 'w+');

        $mode = new \Talal\LabelPrinter\Mode\Escp($stream);
        $printer = new \Talal\LabelPrinter\Printer($mode);

        $badge = new VisitorBadge(
            696,
            590,
            [
                'visitor_name' => 'Stuart Burgess',
                'company_name' => 'A&D Buildings Ltd',
                'validity_date' => '09 Sept 2026',
                'host_name' => 'John Smith',
                'visitor_photo' => __DIR__ . '/fixtures/visitor-photo.png',
                'logo' => __DIR__ . '/fixtures/institution-logo.png'
            ]
        );

        $printer->addCommand($badge)->printLabel();

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals(129905, strlen($output));
        $this->assertEquals(str_repeat(chr(0), 400), substr($output, 6, 400));
        $this->assertEquals(
            '1b6961301b40' . str_repeat('00', 400) . '1b401b6961011b6921011b697a8e0a3e00b802000000001b694d401b6941011b694b091b696423004d0077',
            bin2hex(substr($output, 0, 449))
        );
        $this->assertContains(chr(27) . 'ia' . chr(1), $output);
        $this->assertContains(chr(27) . 'i!' . chr(1), $output);
        $this->assertContains('M' . chr(0), $output);
        $this->assertFalse(strpos($output, chr(27) . 'iS') !== false);
        $this->assertContains(chr(27) . 'iK' . chr(9), $output);
        $this->assertEquals(696, substr_count($output, 'w' . chr(1) . chr(90)));
        $this->assertEquals(696, substr_count($output, 'w' . chr(2) . chr(90)));
        $this->assertEquals(chr(26), substr($output, -1));
    }

    public function testPrintWithoutPhotoThroughEscp()
    {
        $stream = fopen('php://temp', 'w+');

        $mode = new \Talal\LabelPrinter\Mode\Escp($stream);
        $printer = new \Talal\LabelPrinter\Printer($mode);

        $badge = new VisitorBadge(
            696,
            590,
            [
                'visitor_name' => 'Stuart Burgess',
                'company_name' => 'A&D Buildings Ltd',
                'validity_date' => '09 Sept 2026',
                'host_name' => 'John Smith',
                'logo' => __DIR__ . '/fixtures/institution-logo.png'
            ]
        );

        $printer->addCommand($badge)->printLabel();

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals(129905, strlen($output));
        $this->assertEquals(str_repeat(chr(0), 400), substr($output, 6, 400));
        $this->assertContains(chr(27) . 'ia' . chr(1), $output);
        $this->assertContains(chr(27) . 'i!' . chr(1), $output);
        $this->assertContains('M' . chr(0), $output);
        $this->assertFalse(strpos($output, chr(27) . 'iS') !== false);
        $this->assertContains(chr(27) . 'iK' . chr(9), $output);
        $this->assertEquals(696, substr_count($output, 'w' . chr(1) . chr(90)));
        $this->assertEquals(696, substr_count($output, 'w' . chr(2) . chr(90)));
        $this->assertEquals(chr(26), substr($output, -1));
    }

    public function testPrintRotatesRasterRowsForLandscape()
    {
        $badge = new VisitorBadge(
            696,
            509,
            [
                'visitor_name' => 'Stuart Burgess',
                'company_name' => 'A&D Buildings Ltd',
                'validity_date' => '09 Sept 2026',
                'host_name' => 'John Smith',
                'visitor_photo' => __DIR__ . '/fixtures/visitor-photo.png',
                'logo' => __DIR__ . '/fixtures/institution-logo.png'
            ]
        );

        $output = $badge->read();

        $this->assertEquals(129899, strlen($output));
        $this->assertEquals(str_repeat(chr(0), 400), substr($output, 0, 400));
        $this->assertContains(chr(27) . 'ia' . chr(1), $output);
        $this->assertContains(chr(27) . 'i!' . chr(1), $output);
        $this->assertContains('M' . chr(0), $output);
        $this->assertFalse(strpos($output, chr(27) . 'iS') !== false);
        $this->assertContains(chr(27) . 'iK' . chr(9), $output);
        $this->assertEquals(696, substr_count($output, 'w' . chr(1) . chr(90)));
        $this->assertEquals(696, substr_count($output, 'w' . chr(2) . chr(90)));
        $this->assertEquals(chr(26), substr($output, -1));
    }

    public function testPrintToWritesCompleteRasterStream()
    {
        $badge = new VisitorBadge(
            696,
            509,
            [
                'visitor_name' => 'Stuart Burgess',
                'company_name' => 'A&D Buildings Ltd',
                'validity_date' => '09 Sept 2026',
                'host_name' => 'John Smith',
                'visitor_photo' => __DIR__ . '/fixtures/visitor-photo.png',
                'logo' => __DIR__ . '/fixtures/institution-logo.png'
            ]
        );

        // php://temp is a single seekable buffer, not a duplex socket: it
        // cannot loop a write back around to a read the way a real printer
        // connection would reply to a status request. printTo()'s default
        // pre-flight status check (see printTo()'s docblock) would therefore
        // just time out waiting for a reply that can never arrive, so this
        // test explicitly opts out of it to exercise the write path alone.
        $stream = fopen('php://temp', 'w+');

        $badge->printTo($stream, false);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals($badge->read(), $output);
        $this->assertEquals(chr(26), substr($output, -1));
    }

    public function testPrintToWithRequireStatusReplyFailsFastWhenPrinterIsUnresponsive()
    {
        $badge = new VisitorBadge(
            696,
            509,
            [
                'visitor_name' => 'John Doe',
                'company_name' => 'Example Ltd',
                'validity_date' => '2026-09-08',
                'host_name' => 'Jane Smith'
            ]
        );

        $badge = $this->getMock('Talal\LabelPrinter\VisitorBadge', ['requestStatus'], [
            696,
            509,
            [
                'visitor_name' => 'John Doe',
                'company_name' => 'Example Ltd',
                'validity_date' => '2026-09-08',
                'host_name' => 'Jane Smith'
            ]
        ]);
        $badge->expects($this->once())->method('requestStatus')->willReturn(null);

        $this->setExpectedException(
            'RuntimeException',
            'Printer did not respond to a status request before printing'
        );

        // requireStatusReply=true opts into the strict behaviour; it is NOT
        // the default (see printTo()'s docblock: a missing reply is common
        // and not by itself fatal over a raw network connection).
        $stream = fopen('php://temp', 'w+');
        $badge->printTo($stream, true, 3, true);
    }

    public function testPrintToVerifiesStatusByDefaultButProceedsWhenPrinterSimplyDoesNotReply()
    {
        // Default behaviour (requireStatusReply=false): a missing status
        // reply is common on raw network/port-9100 connections and is not
        // by itself treated as proof the printer can't accept the job, so
        // the write should still happen.
        $badge = $this->getMock('Talal\LabelPrinter\VisitorBadge', ['requestStatus'], [
            696,
            509,
            [
                'visitor_name' => 'John Doe',
                'company_name' => 'Example Ltd',
                'validity_date' => '2026-09-08',
                'host_name' => 'Jane Smith'
            ]
        ]);
        $badge->expects($this->once())->method('requestStatus')->willReturn(null);

        $stream = fopen('php://temp', 'w+');
        $badge->printTo($stream);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals($badge->read(), $output);
    }

    public function testRequestStatusSendsInvalidateInitializeThenStatusRequestCommand()
    {
        $badge = new VisitorBadge(
            696,
            509,
            [
                'visitor_name' => 'John Doe',
                'company_name' => 'Example Ltd',
                'validity_date' => '2026-09-08',
                'host_name' => 'Jane Smith'
            ]
        );

        $stream = fopen('php://temp', 'w+');

        $status = $badge->requestStatus($stream, 0.2);

        rewind($stream);
        $written = stream_get_contents($stream);
        fclose($stream);

        $this->assertNull($status);
        $this->assertEquals(
            str_repeat(chr(0), 400) . chr(27) . chr(64) . chr(27) . 'iS',
            $written
        );
    }

    public function testDescribeStatusReportsNoReplyWhenStatusIsNull()
    {
        $badge = new VisitorBadge(
            696,
            509,
            [
                'visitor_name' => 'John Doe',
                'company_name' => 'Example Ltd',
                'validity_date' => '2026-09-08',
                'host_name' => 'Jane Smith'
            ]
        );

        $this->assertEquals(
            'No status reply received (printer command interpreter is not responding).',
            $badge->describeStatus(null)
        );
    }

    public function testDescribeStatusDecodesKnownFields()
    {
        $badge = new VisitorBadge(
            696,
            509,
            [
                'visitor_name' => 'John Doe',
                'company_name' => 'Example Ltd',
                'validity_date' => '2026-09-08',
                'host_name' => 'Jane Smith'
            ]
        );

        $status = str_repeat(chr(0), 32);
        $status[4] = chr(0x41); // Model code: QL-820NWB
        $status[8] = chr(0x01); // Error info 1: no media
        $status[9] = chr(0x10); // Error info 2: cover open
        $status[10] = chr(62);  // Media width: 62mm
        $status[11] = chr(0x4A); // Media type: continuous length tape
        $status[18] = chr(0x02); // Status type: error occurred

        $description = $badge->describeStatus($status);

        $this->assertContains('model=QL-820NWB', $description);
        $this->assertContains('errors=[no media, cover open]', $description);
        $this->assertContains('media width=62mm', $description);
        $this->assertContains('media type=continuous length tape', $description);
        $this->assertContains('status type=error occurred', $description);
    }

    public function testAssertPrinterReadyThrowsWithDecodedErrorBeforeSendingRasterData()
    {
        $badge = $this->getMock('Talal\LabelPrinter\VisitorBadge', ['requestStatus'], [
            696,
            509,
            [
                'visitor_name' => 'John Doe',
                'company_name' => 'Example Ltd',
                'validity_date' => '2026-09-08',
                'host_name' => 'Jane Smith'
            ]
        ]);

        $status = str_repeat(chr(0), 32);
        $status[8] = chr(0x01); // Error info 1: no media

        $badge->expects($this->once())->method('requestStatus')->willReturn($status);

        $this->setExpectedException(
            'RuntimeException',
            'Printer reported an error before printing'
        );

        $stream = fopen('php://temp', 'w+');
        $badge->printTo($stream);
    }

    public function testPrintRejectsPortraitDimensionsThatExceedPrintHead()
    {
        $this->setExpectedException('InvalidArgumentException');

        $badge = new VisitorBadge(
            732,
            1004,
            [
                'visitor_name' => 'Stuart Burgess',
                'company_name' => 'A&D Buildings Ltd',
                'validity_date' => '09 Sept 2026',
                'host_name' => 'John Smith',
                'visitor_photo' => __DIR__ . '/fixtures/visitor-photo.png',
                'logo' => __DIR__ . '/fixtures/institution-logo.png'
            ]
        );

        $badge->read();
    }
}
