<?php

namespace Tests\Wallabag\ApiBundle\Controller;

use Tests\Wallabag\ApiBundle\WallabagApiTestCase;
use Wallabag\CoreBundle\Entity\Tag;

class TagRestControllerTest extends WallabagApiTestCase
{
    public function testGetUserTags()
    {
        $this->client->request('GET', '/api/tags.json');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertGreaterThan(0, $content);
        $this->assertArrayHasKey('id', $content[0]);
        $this->assertArrayHasKey('label', $content[0]);

        return end($content);
    }

    /**
     * @depends testGetUserTags
     */
    public function testDeleteUserTag($tag)
    {
        $tagName = $tag['label'];

        $this->client->request('DELETE', '/api/tags/'.$tag['id'].'.json');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('label', $content);
        $this->assertEquals($tag['label'], $content['label']);
        $this->assertEquals($tag['slug'], $content['slug']);

        $entries = $this->client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findAllByTagId($this->user->getId(), $tag['id']);

        $this->assertCount(0, $entries);

        $tag = $this->client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Tag')
            ->findOneByLabel($tagName);

        $this->assertNull($tag, $tagName.' was removed because it begun an orphan tag');
    }

    public function testDeleteTagByLabel()
    {
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $entry = $this->client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findOneWithTags($this->user->getId());

        $entry = $entry[0];

        $tag = new Tag();
        $tag->setLabel('Awesome tag for test');
        $em->persist($tag);

        $entry->addTag($tag);

        $em->persist($entry);
        $em->flush();

        $this->client->request('DELETE', '/api/tag/label.json', ['tag' => $tag->getLabel()]);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('label', $content);
        $this->assertEquals($tag->getLabel(), $content['label']);
        $this->assertEquals($tag->getSlug(), $content['slug']);

        $entries = $this->client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findAllByTagId($this->user->getId(), $tag->getId());

        $this->assertCount(0, $entries);
    }

    public function testDeleteTagByLabelNotFound()
    {
        $this->client->request('DELETE', '/api/tag/label.json', ['tag' => 'does not exist']);

        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testDeleteTagsByLabel()
    {
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $entry = $this->client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findOneWithTags($this->user->getId());

        $entry = $entry[0];

        $tag = new Tag();
        $tag->setLabel('Awesome tag for tagsLabel');
        $em->persist($tag);

        $tag2 = new Tag();
        $tag2->setLabel('Awesome tag for tagsLabel 2');
        $em->persist($tag2);

        $entry->addTag($tag);
        $entry->addTag($tag2);

        $em->persist($entry);
        $em->flush();

        $this->client->request('DELETE', '/api/tags/label.json', ['tags' => $tag->getLabel().','.$tag2->getLabel()]);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(2, $content);

        $this->assertArrayHasKey('label', $content[0]);
        $this->assertEquals($tag->getLabel(), $content[0]['label']);
        $this->assertEquals($tag->getSlug(), $content[0]['slug']);

        $this->assertArrayHasKey('label', $content[1]);
        $this->assertEquals($tag2->getLabel(), $content[1]['label']);
        $this->assertEquals($tag2->getSlug(), $content[1]['slug']);

        $entries = $this->client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findAllByTagId($this->user->getId(), $tag->getId());

        $this->assertCount(0, $entries);

        $entries = $this->client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findAllByTagId($this->user->getId(), $tag2->getId());

        $this->assertCount(0, $entries);
    }

    public function testDeleteTagsByLabelNotFound()
    {
        $this->client->request('DELETE', '/api/tags/label.json', ['tags' => 'does not exist']);

        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }
}
