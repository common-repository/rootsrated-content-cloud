<?php
interface RootsRatedPosts {

  public function postScheduling($distribution, $rrId, $postType);

  public function postGoLive($distribution, $launchAt, $rrId, $postType);

  public function postRevision($distribution, $rrId, $postType, $scheduledAt);

  public function postUpdate($distribution, $rrId, $postType, $scheduledAt);

  public function postRevoke($rrId, $postType);

  public function getInfo();

  public function distributionUrls();

}
