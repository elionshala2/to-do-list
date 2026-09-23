# To-Do-List
Nje web aplikacion per menaxhimin e detyrave me autentifikim perdoruesi, i ndertuar me HTML, CSS, Javascript, PhP dhe MySQL.

## Kerkesat
- Faqja Kryesore index.html, qe e fton perdoruesin me kriju llogari
- Faqja Login dhe Signup
- Faqja Signup kerkon username, email, password, cpassword
- Passworded duhet te enkriptohen para se te ruhen ne databaze
- Rigjenerimi i session ID ne secilin login
- Per krijimin e taskut te ri kerkohet Titulli, Pershkrimi (opsional), Data (opsionale), Prioriteti (low, medium, high)
- Tasku eshte i lidhur te logged-in user (permes user_id qeles i jashtem)
- Siguria kundrej SQLi
- Perdorimi i htmlspecialchars() ne te gjitha outputet (kundrej XSS)
- Sigrimi qe useri eshte pronari i asaj taske para shikimit/editit/fshirjes
- Faqja loadohet shpejte (<2s)
- Responsive ne llaptop, tablet dhe telefon
- Login/Signup me google, apple, passkey nese ka llogari hyn ne te nese nuk ka e krijon
- Nuk lejohet krijimi i meshum se 1 llogarie me 1 email
- Useri 1 nuk mund ti shoh taskat e Userit 2
- Ne deshtim mesazh i pergjithshem "Kredencialet e pavlefshme"
- Rate-Limiting max 5 tentativa per IP

## Funksionet

# /includes
Brenda folderit /includes gjendet file functions.php i cili ka funksione gjenerale qe perdoren me se shumti.

