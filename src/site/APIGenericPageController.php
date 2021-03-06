<?hh // strict

use HHVM\UserDocumentation\APIClassIndexEntry;
use HHVM\UserDocumentation\APIDefinitionType;
use HHVM\UserDocumentation\APIIndex;
use HHVM\UserDocumentation\APIIndexEntry;
use HHVM\UserDocumentation\APIMethodIndexEntry;
use HHVM\UserDocumentation\APINavData;
use HHVM\UserDocumentation\BuildPaths;
use HHVM\UserDocumentation\HTMLFileRenderable;
use HHVM\UserDocumentation\URLBuilder;

class APIGenericPageController extends WebPageController {
  public async function getTitle(): Awaitable<string> {
    return $this->getRootDefinition()['name'];
  }

  <<__Memoize>>
  final protected function getDefinitionType(): APIDefinitionType {
    return APIDefinitionType::assert(
      $this->getRequiredStringParam('type')
    );
  }

  <<__Memoize>>
  protected function getRootDefinition(): APIIndexEntry {
    $this->redirectIfAPIRenamed();
    $definition_name = $this->getRequiredStringParam('name');

    $index = APIIndex::getIndexForType($this->getDefinitionType());
    if (!array_key_exists($definition_name, $index)) {
      throw new HTTPNotFoundException();
    }
    return $index[$definition_name];
  }

  final protected async function getBody(): Awaitable<XHPRoot> {
    return
      <div class="referencePageWrapper">
          {$this->getInnerContent()}
      </div>;
  }

  protected function getMethodDefinition(): ?APIMethodIndexEntry {
    return null;
  }

  protected function getSideNav(): XHPRoot {
    $path = [
      APINavData::getRootNameForType($this->getDefinitionType()),
      $this->getRootDefinition()['name'],
    ];
    $method = $this->getMethodDefinition();
    if ($method !== null) {
      $path[] = $method['name'];
    }

    return (
      <ui:navbar
        data={APINavData::getNavData()}
        activePath={$path}
        extraNavListClass="apiNavList"
      />
    );
  }

  protected function getHTMLFilePath(): string {
    return $this->getRootDefinition()['htmlPath'];
  }

  final protected function getInnerContent(): XHPRoot {
    return self::invariantTo404(() ==> {
      $path = $this->getHTMLFilePath();
      return
        <div class="innerContent">
          {new HTMLFileRenderable($path, BuildPaths::APIDOCS_HTML)}
        </div>;
    });
  }

  protected function getBreadcrumbs(): :ui:breadcrumbs {
    $type = $this->getDefinitionType();
    $parents = Map {
      'Hack' => '/hack/',
      'Reference' => '/hack/reference/',
      ucwords($type) => '/hack/reference/'.$type.'/',
    };

    $root = $this->getRootDefinition();
    $method = $this->getMethodDefinition();
    if ($method === null) {
      $page = $root['name'];
    } else {
      $page = $method['name'];
      $parents[$root['name']] = $root['urlPath'];
    }

    return <ui:breadcrumbs parents={$parents} currentPage={$page} />;
  }

  protected function redirectIfAPIRenamed(): void {
    invariant(
      self::class === static::class,
      '%s must be overridden by subclasses',
      __FUNCTION__,
    );

    $redirect_to = $this->getRenamedAPI($this->getRequiredStringParam('name'));

    if ($redirect_to === null) {
      return;
    }

    $type = $this->getDefinitionType();
    if ($type === APIDefinitionType::FUNCTION_DEF) {
      $url = URLBuilder::getPathForFunction(shape('name' => $redirect_to));
    } else {
      $url = URLBuilder::getPathForClass(shape(
        'name' => $redirect_to,
        'type' => $type,
      ));
    }

    throw new RedirectException($url);
  }

  protected function getRenamedAPI(string $old): ?string {
    $change_map = ImmMap {
      'ImmMap' => 'HH.ImmMap',
      'ImmSet' => 'HH.ImmSet',
      'ImmVector' => 'HH.ImmVector',
      'Map' => 'HH.Map',
      'Pair' => 'HH.Pair',
      'Set' => 'HH.Set',
      'Vector' => 'HH.Vector',
    };

    return $change_map[$old] ?? null;
  }
}
