# Overview
このプロジェクトは次のサイトを基に作成された、『源氏物語』をJSON形式にしたものと、それを取り扱うための簡単なスクリプトライブラリです。

- 渋谷栄一さんの [源氏物語の世界](http://www.sainet.or.jp/~eshibuya/index.html) 
- 宮脇文経さんの [源氏物語の世界 再編集版](http://www.genji-monogatari.net/) 

日本の代表的な古典文学作品である『源氏物語』をコンピュータで扱い易い形式で配布することにより、この偉大なコンテンツがインターネットをより豊かにすることを願っています。


## Contents

### JSON Files

- `./json/` : 帖ごとに分割されたJSONテキストです。全部で54帖あります。
- `consolidated_genji.zip` :  すべてのテキストを1つのJSONファイルにまとめたものです。内容は分割版と同じです。

### JSON Keys

JSONファイルは次のキーを持っています：
- Chapter : 54帖の各番号です
- Chapter_Title : 各帖のタイトルです
- Chapter_Subtitle : 各帖のサブタイトルです
- Section : 各帖に含まれる章番号です
- Section_Title : 各帖に含まれる章のタイトルです
- Paragraph : 各章に含まれる段落番号です
- Paragraph_Title : 各章に含まれる段落のタイトルです
- Line : 段落の中における行番号です
- LineId : Chapter + Section + Paragraph + Line の番号を繋げた一意の番号です
- Text_Original : 原文テキストです
- Text_Original_Romanized : 原文テキストをローマ字読み下し表記にしたものです
- Text_Shibuya : 渋谷栄一氏による現代語訳です
- Text_Yosano : 青空文庫からとられた与謝野晶子による現代語訳です
- Annotations : 渋谷栄一氏による注釈です

## To be updated

- [ ] Add images from the base site
- [ ] Reflact the latest update in E.Shibuya's notes
- [ ] Add English version texts from [Oxford Archive](https://web.archive.org/web/20170306033600/http://ota.ahds.ac.uk/headers/2245.xml)


## License

このプロジェクトは GNU GPL で公開されている [源氏物語の世界 再編集版](http://www.genji-monogatari.net/) をベースとして作成されたため、それに従ってGPLが適用されます。