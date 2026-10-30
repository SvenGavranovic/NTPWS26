// Quill uređivač umjesto polja <textarea name="sadrzaj">
(function () {
    var polje = document.getElementById('sadrzaj');
    var okvir = document.getElementById('uredjivac');
    if (!polje || !okvir || typeof Quill === 'undefined') {
        return;
    }

    var quill = new Quill(okvir, {
        theme: 'snow',
        modules: {
            // iste oznake kao u HtmlSanitizer.php
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }, 'blockquote'],
                ['link', 'clean']
            ]
        }
    });

    quill.setContents(quill.clipboard.convert({ html: polje.value }));

    polje.classList.add('d-none');
    okvir.classList.remove('d-none');

    document.getElementById('obrazac-vijesti').addEventListener('submit', function () {
        var prazno = quill.getText().trim() === '';
        // Quill 2.0.3: getSemanticHTML() razmake vraća kao &nbsp;
        polje.value = prazno ? '' : quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
    });
})();
