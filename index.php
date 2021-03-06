<?php
/*
ISSO É UM EXEMPLO DE COMO USAR O FIREBASE PARA AUTENTICAÇÃO EM UMA API PHP

A TESE É USAR O AUTH NO LADO DO CLIENTE DO FIREBASE, QUE TEM OS METODOS LA COM VARIOS PROVIDERS QUE NO FINAL DAS CONTAS VAI RETORNAR UM TOKEN JWT QUE TEM AS COISAS QUE ELE EXTRAIU COM BASE NO PROVIDER QUE VC ESCOLHEU. OU SEJA, SE VOCE FIZER LOGIN COM EMAIL E SENHA SO VAI RETORNAR NO TOKEN O EMAIL. SE VOCE FIZER LOGIN COM GOOGLE, VAI RETORNAR EMAIL, NOME, FOTO ETC. ENTÃO VC TEM QUE VALIDAR CADA CASO NO CLIENTE PARA TENTAR RETORNAR O MAXIMO DE INFORMAÇÕES POSSÍVEIS. CASO VOCE QUEIRA EDITAR OU ADICIONAR ALGUMA DESSAS INFORMAÇÕES DO USUARIO, QUANDO VC FAZ UMA AUTENTICAÇÃO O FIREBASE CRIA O UID UNICO DO USUARIO. DA PRA VOCE ADICIONAR OU EDITAR AS INFORMAÇÕES DE CADA USUARIO (CADA UID) TANTO NO CLIENTE TANTO AQUI NO SERVIDOR COM O FIREBASE ADMIN SDK.

ATENÇÃO QUE TEM CASOS ESPECIFICOS QUANDO O CARA POR EXEMPLO FAZ LOGIN PELO GOOGLE PELA PRIMEIRA VEZ, AI DPS QUER FAZER LOGIN PELO EMAIL E SENHA, SENDO QUE O GOOGLE NAO É UM PROVIDER QUE DÁ SENHA PARA O USUARIO. VOCE TEM QUE FAZER NA MAO NO CLIENTE E DAR UM UPDATEPASSWORD() PRA DEFINIR A SENHA DO CARA. DEPOIS DE FAZER ISSO VAI DAR PRA FAZER LOGIN COM A CONTA DO GOOGLE OU PASSANDO EMAIL (MESMO EMAIL DO GOOGLE) E SENHA PREVIAMENTE DEFINIDA.

OUTRA INFORMAÇÃO IMPORTANTE É QUE O FIREBASE DIFERENCIA AS INFORMAÇÕES DO USUARIO ATUAIS (NOME, FOTO, ETC) DOS QUE CHEGARAM DO PROVIDER EXTERNO. ENTAO SE VC FEZ LOGIN PELO GOOGLE, VAI TER NO PROVIDERDATA O NOME E A FOTO QUE VEIO DO GOOGLE, MAS PODE SER QUE O NOME E A FOTO DO SEU USUARIO SEJA ATUALIZADO E FIQUE DIFERENTE. VOCE PODE VER ISSO AQUI NO SERVIDOR IMPRIMINDO O OBJETO DO USUARIO. TEM UM EXEMPLO NO CODIGO.

O QUE VOCÊ VAI FAZER BASICAMENTE É, QUANDO O CLIENTE FIZER AUTH COM O FIREBASE, VAI FAZER UMA REQUISIAÇÃO A ESTA API COM O TOKEN. DO TOKEN, VOCE VAI EXTRAIR O UID E POR EM UMA TABELA DE USUARIOS (SE QUISER PONHA TAMBEM NO FIREBASE, AI VAI FICAR A TABELA DE USUARIOS TANTO NO BANCO DESSA API TANTO NO BANCO DO FIREBASE. VOCE PODE FAZER ISSO FAZENDO OUTRA REQUISIÇÃO NO CLIENTE, MAS DESSA VEZ PRO FIREBASE, OU QUANDO FOR ADD NO BANCO DESSA API, ADD JUNTO NO FIREBASE, USANDO O FIREBASE SDK), TESTANDO SE JA NAO EXISTE O USUARIO, CLARO. CASO JA EXISTA ALGUM USUARIO COM ESSE UID, VOCE VAI APENAS ATUALIZAR AS INFORMAÇÕES DELE (NOME, FOTO, ETC), PEGANDO O USUARIO COM O FIREBASE SDK E O UID QUE CHEGOU NO TOKEN.

TODAS AS REQUISIÇÕES POSTERIORES A API VAO TER QUE TER O TOKEN DO FIREBASE (INCLUSIVE ELE TEM UM TEMPO DE EXPIRAÇÃO, SE EXPIRAR TEM ALGUMA FUNÇÃO NO CLIENTE PRA ATUALIZAR ELE), E TODA VEZ SERÁ TESTADO SE O TOKEN É VALIDO OU NAO. SE FOR FAZ OQ DEVE SER FEITO, SE NAO, RETORNA UM ERRO PARA O CLIENTE.

PARA ADICIONAR PERMISSOES, VOCE VAI ADICIONAR UM CAMPO NA TABELA DOS USUARRIOS DO BANCO, QUE É O NIVEL DE PERMISSAO DELE. QUANDO FOR CRIAR UM NOVO USUARIO NO CLIENTE, VOCE PRIMEIRO FAZ O AUTH (AI VAI GERAR O TOKEN), DEPOIS VOCE COMPLETA AS INFORMAÇÕES QUE PRECISA DEPENDENDO DO PROVIDER (SENHA, TELEFONE, ETC) E POR ULTIMO MARCAR QUAL O NIVEL DO USUARIO. COM TUDO ISSO PRONTO, O CLIENTE FAZ UMA REQUISICAO A ESSA API, QUE SO VAI TER O TOKEN E O NIVEL DO USUARIO. CASO O USUARIO COM ESSE UID JA EXISTA, VAI MUDAR O NIVEL DELE. CASO NAO EXISTA, CRIA UM NOVO USUARIO NO BANCO COM O UID E AS INFORMAÇÕES DO TOKEN, E COM O NIVEL DE PERMISSAO QUE FOI PASSADO PELO CLIENTE NO CORPO. TODA VEZ QUE FOR FEITA UMA REQUISIÇÃO PARA API, ELA VAI VERIFICAR O UID DO TOKEN E ACHAR A PERMISSAO DO USUARIO COM ESSE UID. SE O USUARIO DESSE UID TIVER PERMISSAO, FAZ OQ TEM Q FAZER, SE NAO, DÁ ERRO.
 */
require __DIR__ . '/vendor/autoload.php';

// importa as classes que vai usar do firebase sdk
use Firebase\Auth\Token\Exception\InvalidToken;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

// json com as informações do firebase (nao deixe isso a mostra kkkk)
$serviceAccount = ServiceAccount::fromJsonFile(__DIR__ . '/firebase_credentials.json');
// instancia o firebase sdk
$firebase = (new Factory)
    ->withServiceAccount($serviceAccount)
    ->create();

// token que o firebase retorna no lado do cliente (angular). dps que o usuario for autenticado, manda esse token pra ca

// ESSES OBVIAMENTE VAO SER INVALIDOS PQ VAO EXPIRAR. VA NESSE SITE:
// https://fs1prod-423e0.firebaseapp.com
// FAÇA AUTENTICAÇÃO E NA TELA PRINCIPAL APERTE F12. VAI TER O TOKEN NO CONSOLE (A STRING GIGANTE). COPIE AQUI NO $idToken

$idToken = "eyJhbGciOiJSUzI1NiIsImtpZCI6IjM2OTExYjU1NjU5YTI5YmU3NTYyMDYzYmIxNzc2NWI1NDk4ZTgwZDYifQ.eyJpc3MiOiJodHRwczovL3NlY3VyZXRva2VuLmdvb2dsZS5jb20vZnMxcHJvZC00MjNlMCIsIm5hbWUiOiJjb3JubyIsInBpY3R1cmUiOiJpbWFnZW0iLCJhdWQiOiJmczFwcm9kLTQyM2UwIiwiYXV0aF90aW1lIjoxNTMxMjcyMTY1LCJ1c2VyX2lkIjoic2h5OHRoekxRRGEwV0hQZUpRM1ZiQlNGNko4MiIsInN1YiI6InNoeTh0aHpMUURhMFdIUGVKUTNWYkJTRjZKODIiLCJpYXQiOjE1MzI5NTQzMzMsImV4cCI6MTUzMjk1NzkzMywiZW1haWwiOiJqcDNkcjA0MzZAZ21haWwuY29tIiwiZW1haWxfdmVyaWZpZWQiOnRydWUsImZpcmViYXNlIjp7ImlkZW50aXRpZXMiOnsiZ29vZ2xlLmNvbSI6WyIxMDUzNzMwNTE0MzQ2OTEwNDIxNzAiXSwiZW1haWwiOlsianAzZHIwNDM2QGdtYWlsLmNvbSJdfSwic2lnbl9pbl9wcm92aWRlciI6Imdvb2dsZS5jb20ifX0.h-xXRwgMdirF_pOSG127W2VvgWet1fMAw3Xz1DqXC6HRbWXnGLfnJiGvWkuRiaGkAGszCCR_MrGX1CJ7Jj1O0D-lr9mm_I539Akc9fAQCxKOzjVT0eLczc0zQsETFR8cELZO_FtSEtduLHo_vZOT7ezu9D6EKWTPkU7pGbXe9AY2xWLuJTCgEvZWXQrDDJVWjehH7bQk5rMo2mldyYutM_gplhcHRp_SzckoiiQUbil8JDx42_H60Li6wVQfU2Wy9FbEWAQyO72AVg_Obyur6vhoMdAln61kLVotPk4f0FFxJ7E3RzVbBDMfqpQ5DtMhWMO5PUm7_99twYVMXMBuSg";

$idTokenStringGoogle = 'eyJhbGciOiJSUzI1NiIsImtpZCI6Ijk1OGE0NGZhNThmZGVkZDE1YTE1YmMwMzk1ODM5NGVjMDA0OTdjYzAifQ.eyJpc3MiOiJodHRwczovL3NlY3VyZXRva2VuLmdvb2dsZS5jb20vZnMxcHJvZC00MjNlMCIsIm5hbWUiOiJKb8OjbyBQZWRybyBSaWJlaXJvIiwicGljdHVyZSI6Imh0dHBzOi8vbGg2Lmdvb2dsZXVzZXJjb250ZW50LmNvbS8tWGgwR0ZFZ3FqMmsvQUFBQUFBQUFBQUkvQUFBQUFBQUFXZTQveHAtaUlQMG93VEkvcGhvdG8uanBnIiwiYXVkIjoiZnMxcHJvZC00MjNlMCIsImF1dGhfdGltZSI6MTUzMTI2NTk1OSwidXNlcl9pZCI6InNoeTh0aHpMUURhMFdIUGVKUTNWYkJTRjZKODIiLCJzdWIiOiJzaHk4dGh6TFFEYTBXSFBlSlEzVmJCU0Y2SjgyIiwiaWF0IjoxNTMxMjY1OTU5LCJleHAiOjE1MzEyNjk1NTksImVtYWlsIjoianAzZHIwNDM2QGdtYWlsLmNvbSIsImVtYWlsX3ZlcmlmaWVkIjp0cnVlLCJmaXJlYmFzZSI6eyJpZGVudGl0aWVzIjp7Imdvb2dsZS5jb20iOlsiMTA1MzczMDUxNDM0NjkxMDQyMTcwIl0sImVtYWlsIjpbImpwM2RyMDQzNkBnbWFpbC5jb20iXX0sInNpZ25faW5fcHJvdmlkZXIiOiJnb29nbGUuY29tIn19.H_ZMpX0okbP_Tk7VhuanyrLRzw-PtfB7mcug83RuX1K6N4ApmZE0ws7_PMm8NmYa-Tdwf7fuHdWdm5NAlTIZGPaagS6sIienL3yUZDKt7NeRVQgnAJ0KU4CSc1hGP3LGa-s78RkSyZ4Ln9Vl2_2ETmfYG_M3_U049wzWZ01DNXi7DD_KNU7qCrjLHpnqX3L4ASDB7jQjlXAOt5TJcSTR7MIwwZQtCQbcvWUUgN8mlH0AwczVUxfsMoPV9r6SZQ4vQloXbI2VsTCwpNtDv3AMCrcxtlvKeC9-Pm7JsNAo3UnT5t5soX7JVPIqa6V3phnQEe-HY_s1RjyVB9IbiB-2dQ';

$idTokenStringEmail = 'eyJhbGciOiJSUzI1NiIsImtpZCI6Ijk1OGE0NGZhNThmZGVkZDE1YTE1YmMwMzk1ODM5NGVjMDA0OTdjYzAifQ.eyJpc3MiOiJodHRwczovL3NlY3VyZXRva2VuLmdvb2dsZS5jb20vZnMxcHJvZC00MjNlMCIsImF1ZCI6ImZzMXByb2QtNDIzZTAiLCJhdXRoX3RpbWUiOjE1MzEyNzAxOTEsInVzZXJfaWQiOiJ1ZU04RVIwcWR3UUt1RUZFNEM0WlEwZEFYNEgyIiwic3ViIjoidWVNOEVSMHFkd1FLdUVGRTRDNFpRMGRBWDRIMiIsImlhdCI6MTUzMTI3MDE5MSwiZXhwIjoxNTMxMjczNzkxLCJlbWFpbCI6ImpvYXppbkBnbWFpbC5jb20iLCJlbWFpbF92ZXJpZmllZCI6ZmFsc2UsImZpcmViYXNlIjp7ImlkZW50aXRpZXMiOnsiZW1haWwiOlsiam9hemluQGdtYWlsLmNvbSJdfSwic2lnbl9pbl9wcm92aWRlciI6InBhc3N3b3JkIn19.N1KUWNOOoPr8zQOGhl2GOy2d0sspXurRGg3JLBYSgezPzUXCQiBMXLC3g-ueJ7IB2XE2UaLeVUMNN7Tczt_KJACqwYQMmmSyH8Xd0Ceh_FPHFnBUmooxuRxLrXyJA29x0aiU6aFhf4qr35gdOrRB6-ewnpP4p89iN6Fe72dkhguJRDuqVExsKW9XvAvARj5iSvr70uLWTThJdmU_OVkgdE7G4rzwGQHS1pXEIdJoe5mGL49xDzlWd_JPdSZmHGI0s8s1b_ATDsJO-_5sU6dy_c9Nh9GkGdc810mXLIfTjVwKTiJlTl3KK9JdHhhTSjb_Ts3GjBE7RTk3cijzf7Y0pQ';

// funções prontas para trabalhar com os usuarios

function getFirebaseAuthUser($uid, $firebase)
{
    $auth = $firebase->getAuth();
    $user = $firebase->getAuth()->getUser($uid);
    return $user;
}

function getFirebaseAuthUsers()
{
    $users = $auth->listUsers($defaultMaxResults = 1000, $defaultBatchSize = 1000);

    foreach ($users as $user) {
        /** @var \Kreait\Firebase\Auth\UserRecord $user */
        echo "<pre>" . print_r($user, true) . "</pre>" . "<br>";
    }

    //array_map(function (\Kreait\Firebase\Auth\UserRecord $user) {}, iterator_to_array($users));

}

// verifica se o token é válido. se for, retorna o uid do usuario. se nao, retorna o erro (string);
function verifyFirebaseAuthToken($firebase, $token)
{
    // try catch pq o token pode nao ser válido. se não for, entra no catch e retorna o erro
    try {
        $verifiedIdToken = $firebase->getAuth()->verifyIdToken($token);
        // id unico do usuario nessa aplicação firebase. teste se nao ja tem algum usuario no bd com esse id
        // se n tiver, adicione um novo usuario com esse id e pegue os outros troços (nome, email, foto)
        $uid = $verifiedIdToken->getClaim('sub');
        return $uid;
    } catch (InvalidToken $e) {
        return "Error: " . $e->getMessage();
    }
}

// TODO - EDIT USER

//$response = verifyFirebaseAuthToken($firebase, $idToken);

// token nao é válido
/*
if (strpos($response, 'Error') !== false) {
echo 'nao é válido';
}
else {
echo 'é válido';
}
 */

// imprime o usuario no firebase do jwt que vc passar, se ele for válido
print_r(getFirebaseAuthUser(verifyFirebaseAuthToken($firebase, $idToken), $firebase));
