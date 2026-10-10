// abas e categorias do modal "+N", iguais às do sistema antigo (HomeController do wssports.bet,
// array $cotacao): aba → categoria → códigos de cotação na ordem de exibição; o nome de cada
// mercado vem da API (campo "mercado")

export const abas_cotacoes = [
    {
        chave: 'favoritas',
        titulo: 'Favoritas',
        categorias: [
            { titulo: 'MAIS APOSTADAS', codigos: ['odd1', 'odd3', 'odd4', 'odd116', 'odd2', 'odd115', 'odd10', 'odd13', 'odd11', 'odd135', 'odd139', 'odd15', 'odd17', 'odd123', 'odd124'] },
        ],
    },
    {
        chave: '90 min',
        titulo: '90 min',
        categorias: [
            { titulo: 'VENCEDOR', codigos: ['odd1', 'odd2', 'odd3'] },
            { titulo: 'AMBAS AS EQUIPES', codigos: ['odd4', 'odd7'] },
            { titulo: 'DUPLA CHANCE', codigos: ['odd10', 'odd11', 'odd13'] },
            { titulo: 'HANDICAP ASIÁTICO', codigos: ['odd15', 'odd16', 'odd17', 'odd147', 'odd148', 'odd149', 'odd150', 'odd151', 'odd152', 'odd153', 'odd154', 'odd155', 'odd156', 'odd157', 'odd158', 'odd159', 'odd160', 'odd161', 'odd162', 'odd163'] },
            { titulo: 'INTERVALO | FINAL DE JOGO', codigos: ['odd18', 'odd19', 'odd20', 'odd21', 'odd22', 'odd23', 'odd24', 'odd25', 'odd26'] },
            { titulo: 'RESULTADO EXATO', codigos: ['odd27', 'odd28', 'odd29', 'odd30', 'odd31', 'odd32', 'odd33', 'odd34', 'odd35', 'odd36', 'odd37', 'odd38', 'odd39', 'odd40', 'odd41', 'odd42', 'odd43', 'odd44', 'odd45', 'odd46', 'odd47', 'odd48', 'odd49', 'odd50', 'odd51', 'odd52', 'odd53', 'odd54', 'odd55', 'odd56', 'odd57', 'odd58', 'odd59', 'odd60'] },
            { titulo: 'GOLS MAIS/MENOS', codigos: ['odd115', 'odd116', 'odd117', 'odd118', 'odd119', 'odd120', 'odd121', 'odd122', 'odd123', 'odd124'] },
            { titulo: 'GOLS PAR/ÍMPAR', codigos: ['odd125', 'odd127'] },
            { titulo: 'TOTAL DE GOLS', codigos: ['odd85', 'odd86', 'odd87', 'odd88', 'odd89', 'odd90', 'odd91'] },
            { titulo: 'VENCEDOR E AMBAS EQUIPES', codigos: ['odd135', 'odd136', 'odd137', 'odd138', 'odd139', 'odd140'] },
            { titulo: 'RESULTADO E TOTAL DE GOLS', codigos: ['odd141', 'odd142', 'odd143', 'odd144', 'odd145', 'odd146'] },
            { titulo: 'EMPATE ANULA APOSTA', codigos: ['odd164', 'odd165'] },
            { titulo: 'ESCANTEIOS ACIMA/ABAIXO', codigos: ['odd166', 'odd167', 'odd168', 'odd169', 'odd170', 'odd171', 'odd172', 'odd173', 'odd174', 'odd175', 'odd176', 'odd177', 'odd178', 'odd179', 'odd180', 'odd181', 'odd182', 'odd183', 'odd184', 'odd185', 'odd186', 'odd187', 'odd188', 'odd189', 'odd190', 'odd191', 'odd192', 'odd193', 'odd194', 'odd195', 'odd196', 'odd197', 'odd198', 'odd199', 'odd200', 'odd201'] },
            { titulo: 'ESCANTEIOS EXATOS', codigos: ['odd202', 'odd203', 'odd204', 'odd205', 'odd206', 'odd207', 'odd208', 'odd209', 'odd210', 'odd211', 'odd212', 'odd213', 'odd214', 'odd215', 'odd216', 'odd217', 'odd218', 'odd219'] },
            { titulo: 'TOTAL DE ESCANTEIOS', codigos: ['odd220', 'odd221', 'odd222', 'odd223', 'odd224'] },
            { titulo: 'TIME IMPAR/PAR', codigos: ['odd245', 'odd246', 'odd247', 'odd248'] },
            { titulo: 'AMBAS MARCAM, 1º TEMPO / 2º TEMPO', codigos: ['odd251', 'odd252', 'odd253', 'odd254'] },
            { titulo: 'TEMPO COM MAIS GOLS', codigos: ['odd261', 'odd262', 'odd263'] },
            { titulo: 'TIME E TEMPO COM MAIS GOLS', codigos: ['odd264', 'odd265', 'odd266', 'odd267', 'odd268', 'odd269'] },
            { titulo: 'TIME SEM SOFRER GOL', codigos: ['odd270', 'odd272'] },
            { titulo: 'TIME SOFRE GOL', codigos: ['odd271', 'odd273'] },
            { titulo: 'MARGEM DE VITÓRIA', codigos: ['odd274', 'odd275', 'odd276', 'odd277', 'odd278', 'odd279', 'odd280', 'odd281', 'odd282', 'odd283'] },
            { titulo: 'TIME - TOTAL DE GOLS', codigos: ['odd284', 'odd285', 'odd286', 'odd287', 'odd288', 'odd289', 'odd290', 'odd291', 'odd292', 'odd293', 'odd294', 'odd295', 'odd296', 'odd297', 'odd298', 'odd299', 'odd300', 'odd301', 'odd302', 'odd303', 'odd304', 'odd305', 'odd306', 'odd307', 'odd308', 'odd309', 'odd310', 'odd311', 'odd312', 'odd313', 'odd314', 'odd315', 'odd316', 'odd317', 'odd318', 'odd319', 'odd320', 'odd321', 'odd322', 'odd323'] },
        ],
    },
    {
        chave: '1º Tempo',
        titulo: '1º Tempo',
        categorias: [
            { titulo: 'VENCEDOR - 1º TEMPO', codigos: ['odd129', 'odd130', 'odd131'] },
            { titulo: 'AMBAS EQUIPES - 1º TEMPO', codigos: ['odd5', 'odd8'] },
            { titulo: 'DUPLA CHANCE - 1º TEMPO', codigos: ['odd12', 'odd14'] },
            { titulo: 'HANDICAP ASIÁTICO - 1º TEMPO', codigos: ['odd225', 'odd226', 'odd227', 'odd228', 'odd229', 'odd230', 'odd231', 'odd232', 'odd233', 'odd234', 'odd235', 'odd236', 'odd237', 'odd238', 'odd239', 'odd240', 'odd241', 'odd242', 'odd243', 'odd244'] },
            { titulo: 'RESULTADO EXATO - 1° TEMPO', codigos: ['odd61', 'odd62', 'odd63', 'odd64', 'odd65', 'odd66', 'odd67', 'odd68', 'odd69', 'odd70', 'odd71', 'odd72', 'odd73', 'odd74', 'odd75', 'odd76', 'odd77', 'odd78', 'odd79', 'odd80', 'odd81', 'odd82', 'odd83', 'odd84'] },
            { titulo: 'GOLS MAIS/MENOS - 1º TEMPO', codigos: ['odd92', 'odd93', 'odd94', 'odd95', 'odd96'] },
            { titulo: 'GOLS PAR/ÍMPAR - 1º TEMPO', codigos: ['odd126', 'odd128'] },
            { titulo: 'TOTAL DE GOLS - 1° TEMPO', codigos: ['odd97', 'odd98', 'odd99', 'odd100', 'odd101', 'odd102'] },
            { titulo: 'VENCEDOR AMBAS EQUIPES - 1º TEMPO', codigos: ['odd255', 'odd256', 'odd257', 'odd258', 'odd259', 'odd260'] },
        ],
    },
    {
        chave: '2º Tempo',
        titulo: '2º Tempo',
        categorias: [
            { titulo: 'VENCEDOR - 2º TEMPO', codigos: ['odd132', 'odd133', 'odd134'] },
            { titulo: 'AMBAS EQUIPES - 2º TEMPO', codigos: ['odd6', 'odd9'] },
            { titulo: 'GOLS MAIS/MENOS - 2° TEMPO', codigos: ['odd103', 'odd104', 'odd105', 'odd106', 'odd107', 'odd108', 'odd109', 'odd110'] },
            { titulo: 'TOTAL DE GOLS - 2° TEMPO', codigos: ['odd111', 'odd112', 'odd113', 'odd114'] },
            { titulo: 'GOLS PAR/ÍMPAR - 2º TEMPO', codigos: ['odd249', 'odd250'] },
        ],
    },
];
