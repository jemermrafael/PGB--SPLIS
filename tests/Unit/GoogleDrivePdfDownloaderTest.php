<?php

namespace Tests\Unit;

use App\Services\GoogleDrivePdfDownloader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleDrivePdfDownloaderTest extends TestCase
{
    public function test_google_docs_editor_url_is_exported_as_docx(): void
    {
        $docxBody = "PK\x03\x04".str_repeat('x', 64);

        Http::fake([
            'docs.google.com/document/d/*/export?format=docx' => Http::response($docxBody, 200, [
                'Content-Type' => 'application/octet-stream',
            ]),
        ]);

        $url = 'https://docs.google.com/document/d/1JVdCaWgdx-yzWYce2n8D9S6Hl87ul_Ue/edit?usp=sharing&rtpof=true&sd=true';

        $result = app(GoogleDrivePdfDownloader::class)->downloadFile($url);

        $this->assertSame('docx', $result['extension']);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $result['mime'],
        );
        $this->assertSame($docxBody, $result['contents']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/document/d/1JVdCaWgdx-yzWYce2n8D9S6Hl87ul_Ue/export')
                && str_contains($request->url(), 'format=docx');
        });
    }

    public function test_google_docs_export_detects_pdf_content_type(): void
    {
        Http::fake([
            'docs.google.com/document/d/*/export*' => Http::response('%PDF-1.5 fake', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $result = app(GoogleDrivePdfDownloader::class)->downloadFile(
            'https://docs.google.com/document/d/ABC123/edit',
        );

        $this->assertSame('pdf', $result['extension']);
        $this->assertSame('application/pdf', $result['mime']);
    }

    public function test_list_folder_files_parses_embedded_folder_view(): void
    {
        $html = <<<'HTML'
            <html><body>
                <a href="https://drive.google.com/file/d/fileAaa111/view?usp=drive_web">Report One.pdf</a>
                <a href="https://drive.google.com/file/d/fileBbb222/view?usp=drive_web">Report Two.pdf</a>
                <a href="https://docs.google.com/document/d/docCcc333/edit">Notes Doc</a>
            </body></html>
        HTML;

        Http::fake([
            'drive.google.com/embeddedfolderview*' => Http::response($html, 200),
        ]);

        $files = app(GoogleDrivePdfDownloader::class)->listFolderFiles(
            'https://drive.google.com/drive/folders/folderXyz999?usp=sharing',
        );

        $this->assertCount(3, $files);
        $this->assertSame('fileAaa111', $files[0]['id']);
        $this->assertSame('Report One.pdf', $files[0]['name']);
        $this->assertSame('file', $files[0]['kind']);
        $this->assertSame('docCcc333', $files[2]['id']);
        $this->assertSame('document', $files[2]['kind']);
    }
}
