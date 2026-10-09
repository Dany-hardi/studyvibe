# Bulk import of questions and pictures

One ZIP file, a clear format, checked before anything is written.

## The package

```
mon-pack.zip
  questions.csv        one CSV (the first one found, anywhere in the zip)
  images/              optional, any folder name or none; PNG, JPG, GIF, WebP
```

A plain `.csv` also works (no pictures then). A ready-to-fill example: *Import en lot → Télécharger un modèle de pack (ZIP)* in a live session.

## questions.csv

Header names are forgiving (accents, case, French or English). Columns, in any order:

| Column | Required | Meaning |
|---|---|---|
| `question` | yes | The statement. A quoted cell may contain line breaks (a code snippet) and `$...$` maths |
| `option_a` ... `option_d` | for multiple choice | Live sessions also accept only A and B (true/false) |
| `correct` | yes | `A`, `B`, `C` or `D` for multiple choice; the accepted answer for a written question (`5\|cinq` for alternatives, `2.5~0.1` for a tolerance) |
| `explanation` | no | Shown in the correction |
| `type` | no | `mcq` or `written` (guessed when absent: no options = written) |
| `image` | no | Name of a picture inside the zip, for example `figure1.png`. Case does not matter, the folder does not matter |
| `time_limit` | no | Seconds for this question (5 to 3600). Empty = the session's default |

Use `,` or `;` as separator, with or without a UTF-8 BOM.

## What happens

1. **Check** (preview): the package is read and described line by line: questions found, pictures matched, errors, warnings. Nothing is written.
2. **Import**: the package is checked again (the browser's preview is not trusted) and written **all or nothing**: pictures first, then every question in one transaction. If anything fails the pictures just saved are removed.

Errors that block the import: a picture named in the CSV that is not in the zip, a file that is not really a picture, an incomplete multiple-choice line, an invalid correct letter, no CSV, a corrupted zip, too many questions or files. Warnings that do not block: a picture nobody uses, a duplicate file name, a bad `time_limit` (ignored), pictures in a lesson or course quiz (those do not carry pictures).

## Limits and safety

300 questions, 400 files, 120 MB unpacked, 8 MB per picture. Names with folders or `..` are never used as paths: pictures are matched by file name and unpacked under random names in a private temporary folder, which is deleted afterwards. Every picture is verified on the file itself, scaled to 1600 px and also kept in the database. The PHP setting `upload_max_filesize` (64 MB in `.htaccess`; the built-in dev server defaults to 2 MB) limits the size of the zip itself.

## Where

Live session panel (teacher dashboard) → *Import en lot*. Endpoint: `teacher/bulk-import.php` (`mode=preview|commit`, `type=live|lesson|course`). Code: `lib/BulkPackage.php`, `QuestionImporter.php`. Test: `php tests/integration/bulk_import.php`.
