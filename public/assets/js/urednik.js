/*
 * Quill rich-text uređivač za sadržaj vijesti.
 *
 * Obrazac i dalje šalje obično polje <textarea name="sadrzaj">: Quill se
 * prikazuje umjesto njega, a prije slanja se HTML iz uređivača upiše u polje.
 * Bez JavaScripta ostaje vidljivo obično polje (obrazac i dalje radi).
 */
(function () {
    var polje = document.getElementById('sadrzaj');
    var okvir = document.getElementById('uredjivac');
    if (!polje || !okvir || typeof Quill === 'undefined') {
        return;
    }

    var quill = new Quill(okvir, {
        theme: 'snow',
        modules: {
            // Samo oznake koje dopušta i HTMLPurifier na poslužitelju (HtmlSanitizer.php)
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }, 'blockquote'],
                ['link', 'clean']
            ]
        }
    });

    // Postojeći sadržaj (već očišćen na poslužitelju) učitava se u uređivač
    quill.setContents(quill.clipboard.convert({ html: polje.value }));

    polje.classList.add('d-none');
    okvir.classList.remove('d-none');

    document.getElementById('obrazac-vijesti').addEventListener('submit', function () {
        var prazno = quill.getText().trim() === '';
        // getSemanticHTML daje standardni HTML (<ul>/<ol>); Quill 2.0.3 razmake
        // pretvara u &nbsp;, pa ih vraćamo u obične razmake
        polje.value = prazno ? '' : quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
    });
})();
