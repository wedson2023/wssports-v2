import { useEffect, useState } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import { Color, TextStyle } from '@tiptap/extension-text-style';

import useTelaMobile from '../../hooks/useTelaMobile';

import {
    Bar, Button, Colors, Counter, Divider, Empty, Error, Footer, Frame, Preview, PreviewToggle, Select, Side, SideTitle, Swatch, Workspace,
} from './styles';

// limite do texto visível (FR-018); a formatação não conta
const MAXIMO = 10000;

// cores do texto: as 6 do tema e dois neutros
const cores = ['#000000', '#757575', '#c40808', '#d0af01', '#008000', '#006eb1', '#fe6a00', '#b91552'];

const alinhamentos = [
    { valor: 'left', icone: 'format_align_left', titulo: 'Alinhar à esquerda' },
    { valor: 'center', icone: 'format_align_center', titulo: 'Centralizar' },
    { valor: 'right', icone: 'format_align_right', titulo: 'Alinhar à direita' },
    { valor: 'justify', icone: 'format_align_justify', titulo: 'Justificar' },
];

// editor das regras da banca (spec 021, FR-017, research R-07): TipTap com a barra parecida com a de
// um editor de documentos (estilo do parágrafo, negrito, itálico, sublinhado, cor, listas,
// alinhamento, link, desfazer e refazer), contador de caracteres e a prévia (ao lado no computador;
// pelo botão "Ver como fica" no celular). Só gera o que o
// servidor aceita (R-08); vazio vira ""
export default function RulesEditor({ valor, ao_mudar, erro = null }) {
    const [previa, definir_previa] = useState(false);
    const [cores_abertas, definir_cores_abertas] = useState(false);
    const e_mobile = useTelaMobile();

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                blockquote: false,
                code: false,
                codeBlock: false,
                horizontalRule: false,
                link: { openOnClick: false, autolink: true, HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer' } },
            }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            TextStyle,
            Color,
        ],
        content: valor ?? '',
        // a barra mostra o que está marcado: redesenha a cada mudança do editor
        shouldRerenderOnTransaction: true,
        onUpdate: ({ editor: atual }) => ao_mudar(atual.isEmpty ? '' : atual.getHTML()),
    });

    // valor trocado de fora (Desfazer, recarga): o editor acompanha sem avisar mudança
    useEffect(() => {
        if (!editor) return;

        const atual = editor.isEmpty ? '' : editor.getHTML();

        if ((valor ?? '') !== atual) editor.commands.setContent(valor ?? '', { emitUpdate: false });
    }, [editor, valor]);

    if (!editor) return null;

    const caracteres = editor.getText().length;
    const cadeia = () => editor.chain().focus();

    const estilo_atual = () => {
        if (editor.isActive('heading', { level: 2 })) return 'h2';
        if (editor.isActive('heading', { level: 3 })) return 'h3';

        return 'p';
    };

    const mudar_estilo = (estilo) => {
        if (estilo === 'p') cadeia().setParagraph().run();
        else cadeia().toggleHeading({ level: estilo === 'h2' ? 2 : 3 }).run();
    };

    const editar_link = () => {
        const atual = editor.getAttributes('link').href ?? '';
        const endereco = window.prompt('Endereço do link (vazio remove):', atual);

        if (endereco === null) return;

        if (endereco.trim() === '') cadeia().extendMarkRange('link').unsetLink().run();
        else cadeia().extendMarkRange('link').setLink({ href: endereco.trim() }).run();
    };

    const botao = (icone, titulo, ativo, ao_clicar, desabilitado = false) => (
        <Button type="button" title={titulo} aria-label={titulo} aria-pressed={ativo} $ativo={ativo} disabled={desabilitado || (previa && e_mobile)} onClick={ao_clicar}>
            <i className="material-icons" aria-hidden="true">{icone}</i>
        </Button>
    );

    const html = editor.isEmpty ? '' : editor.getHTML();

    return (
        <>
            <Workspace>
                <Frame $erro={Boolean(erro)}>
                    <Bar role="toolbar" aria-label="Formatação das regras">
                        <Select value={estilo_atual()} disabled={previa && e_mobile} onChange={(evento) => mudar_estilo(evento.currentTarget.value)} aria-label="Estilo do parágrafo">
                            <option value="p">Parágrafo</option>
                            <option value="h2">Título</option>
                            <option value="h3">Subtítulo</option>
                        </Select>
                        <Divider />
                        {botao('format_bold', 'Negrito', editor.isActive('bold'), () => cadeia().toggleBold().run())}
                        {botao('format_italic', 'Itálico', editor.isActive('italic'), () => cadeia().toggleItalic().run())}
                        {botao('format_underlined', 'Sublinhado', editor.isActive('underline'), () => cadeia().toggleUnderline().run())}
                        <Colors>
                            {botao('format_color_text', 'Cor do texto', cores_abertas, () => definir_cores_abertas((aberta) => !aberta))}
                            {cores_abertas && (
                                <div role="menu">
                                    {cores.map((cor) => (
                                        <Swatch
                                            key={cor}
                                            type="button"
                                            role="menuitem"
                                            title={cor}
                                            $cor={cor}
                                            onClick={() => {
                                                if (cor === '#000000') cadeia().unsetColor().run();
                                                else cadeia().setColor(cor).run();
                                                definir_cores_abertas(false);
                                            }}
                                        />
                                    ))}
                                </div>
                            )}
                        </Colors>
                        <Divider />
                        {botao('format_list_bulleted', 'Lista', editor.isActive('bulletList'), () => cadeia().toggleBulletList().run())}
                        {botao('format_list_numbered', 'Lista numerada', editor.isActive('orderedList'), () => cadeia().toggleOrderedList().run())}
                        <Divider />
                        {alinhamentos.map(({ valor: alinhamento, icone, titulo }) => (
                            <span key={alinhamento}>{botao(icone, titulo, editor.isActive({ textAlign: alinhamento }), () => cadeia().setTextAlign(alinhamento).run())}</span>
                        ))}
                        <Divider />
                        {botao('link', 'Link', editor.isActive('link'), editar_link)}
                        {botao('undo', 'Desfazer', false, () => cadeia().undo().run(), !editor.can().undo())}
                        {botao('redo', 'Refazer', false, () => cadeia().redo().run(), !editor.can().redo())}
                    </Bar>
                    {previa && e_mobile
                        ? <Preview dangerouslySetInnerHTML={{ __html: html }} />
                        : <EditorContent editor={editor} />}
                </Frame>
                {/* no computador, a prévia fica ao lado e acompanha o que é escrito */}
                {!e_mobile && (
                    <Side aria-label="Como fica no site">
                        <SideTitle>
                            <i className="material-icons" aria-hidden="true">visibility</i>
                            Como fica no site
                        </SideTitle>
                        {html ? <Preview dangerouslySetInnerHTML={{ __html: html }} /> : <Empty>As regras aparecem aqui enquanto você escreve.</Empty>}
                    </Side>
                )}
            </Workspace>
            <Footer>
                {e_mobile ? (
                    <PreviewToggle type="button" onClick={() => definir_previa((atual) => !atual)}>
                        <i className="material-icons" aria-hidden="true">{previa ? 'edit' : 'visibility'}</i>
                        {previa ? 'Voltar a editar' : 'Ver como fica'}
                    </PreviewToggle>
                ) : <span />}
                <Counter $passou={caracteres > MAXIMO}>
                    {caracteres.toLocaleString('pt-BR')} de {MAXIMO.toLocaleString('pt-BR')} caracteres
                </Counter>
            </Footer>
            {erro && <Error role="alert">{erro}</Error>}
        </>
    );
}
